<?php

namespace srag\Plugins\Opencast\Util\Player;

use xoctMedia;
use xoctException;
use xoctConf;
use xoctSecureLink;
use xoctEvent;
use xoctAttachment;
use Metadata;
use DateTime;
use DateTimeZone;

/**
 * Class DefaultPlayerDataBuilder
 * @package srag\Plugins\Opencast\Util\Player
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class StandardPlayerDataBuilder extends PlayerDataBuilder
{
    /**
     * @return array
     * @throws xoctException
     */
    public function buildStreamingData() : array
    {
        // get all media url that match the given tags and flavors
        $media = $this->event->publications()->getPlayerPublications();
	
	    // do NOT filter only media with mimetype video/
	    /*
	    $media = array_values(array_filter($media, function (xoctMedia $medium) {
            return strpos($medium->getMediatype(), xoctMedia::MEDIA_TYPE_VIDEO) !== false;
        }))
        */

        if (empty($media)) {
            throw new xoctException(xoctException::NO_STREAMING_DATA);
        }

        list($duration, $streams) = $this->buildStreams($media);

        $data = [
            "streams" => array_values($streams),
            "metadata" => [
                "title" => $this->event->getTitle(),
                "duration" => $duration,
                "preview" => $this->event->publications()->getThumbnailUrl()
            ]
        ];
        $data['frameList'] = $this->buildSegments($this->event);

        return $data;
    }

    /**
     * @param array $media
     * @return array
     * @throws xoctException
     */
    protected function buildStreams(array $media) : array
    {
        $duration = 0;
        $streams = [];
        $presentations_mp4 = [];
        $presentations_hls= [];
        $presentations_dash= [];
        $presenters_mp4 = [];
        $presenters_hls= [];
        $presenters_dash= [];

        // loop over all media file, which are filtered by tag or flavor in configuration file
        foreach ($media as $medium) {
 	  $master_playlist = $medium->is_master_playlist();
	  if($master_playlist) {
            $duration = $duration ?: $medium->getDuration();
            $mimetype = $medium->getMediatype();
	    $ID       = $medium->getId();
            $source = $this->buildSource($medium, $duration);
            
            // fill arrays for presentation and/or presenter media
            // check mediatype and presenter/presentation to filter mp4, hls and dash
	    if ($medium->getRole() == xoctMedia::ROLE_PRESENTER) {
                if ($mimetype == "application/x-mpegURL") {
                    $presenters_hls[$ID] = $source;
		}
                else if ($mimetype == "application/dash+xml") {
                    $presenters_dash[$ID] = $source;
		}
		else if ($mimetype == "video/mp4") {
                    $presenters_mp4[$ID] = $source;
		}
            } else if ($medium->getRole() == xoctMedia::ROLE_PRESENTATION) {
                if ($mimetype == "application/x-mpegURL") {
                    $presentations_hls[$ID] = $source;
		}
                else if ($mimetype == "application/dash+xml") {
                    $presentations_dash[$ID] = $source;
		}
                else if ($mimetype == "video/mp4") {
                    $presentations_mp4[$ID] = $source;
		}
            //} else if ($medium->getRole() == xoctMedia::ROLE_PRESENTATION_12 {;
	    }
	  }
	}
	
        //if (count($presenters_mp4) > 0 || count($presenters_hls) > 0 || count($presenters_dash) > 0 ) { 
	if (count($presenters_mp4) > 0 && count($presenters_hls))  { 
            $streams[] = [
                "content" => self::ROLE_MASTER,
                "sources" => [
                     "hls" => array_values($presenters_hls),
                     "mp4" => array_values($presenters_mp4)
                 ],
            ];
	}
        else if (count($presenters_hls) > 0 )  { 
            $streams[] = [
                "content" => self::ROLE_MASTER,
                 "sources" => [
                     "hls" => array_values($presenters_hls)
                 ],
            ];
	}
	else if (count($presenters_mp4) > 0 )  { 
            $streams[] = [
                "content" => self::ROLE_MASTER,
                 "sources" => [
                     "mp4" => array_values($presenters_mp4)
                 ],
            ];
        }

        //if (count($presentations_mp4)> 0 || count($presentations_hls) > 0 || count($presentations_dash) > 0 ) {
        if (count($presentations_mp4) > 0 && count($presentations_hls) )  { 
            $streams[] = [
                "content" => self::ROLE_SLAVE,
                "sources" => [
                     "hls" => array_values($presentations_hls),
                     "mp4" => array_values($presentations_mp4)
                ],
            ];
	}
	else if (count($presentations_hls) > 0 )  {
            $streams[] = [
                "content" => self::ROLE_SLAVE,
                 "sources" => [
                     "hls" => array_values($presentations_hls)
                 ],
            ];
        }	
        else if (count($presentations_mp4) > 0 )  {
            $streams[] = [
                "content" => self::ROLE_SLAVE,
                 "sources" => [
                     "mp4" => array_values($presentations_mp4)
                 ],
            ];
        }
       
        return array($duration, $streams);
    }

    /**
     * @param     $medium
     * @param int $duration
     * @return array
     * @throws xoctException
     */
    private function buildSource($medium, int $duration) : array
    {
        $url = xoctConf::getConfig(xoctConf::F_SIGN_PLAYER_LINKS) ? xoctSecureLink::signPlayer($medium->getUrl(),
            $duration) : $medium->getUrl();
        return [
            "src" => $url,
            "mimetype" => $medium->getMediatype(),
            "res" => [
                "w" => $medium->getWidth(),
                "h" => $medium->getHeight()
            ]
        ];
    }

    /**
     * @param xoctEvent $xoctEvent
     *
     * @return array
     * @throws xoctException
     */
    protected function buildSegments(xoctEvent $xoctEvent) : array
    {
        $frameList = [];
        $segments = $xoctEvent->publications()->getSegmentPublications();
        if (count($segments) > 0) {
            $segments = array_reduce($segments, function (array &$segments, xoctAttachment $segment) {
                if (!isset($segments[$segment->getRef()])) {
                    $segments[$segment->getRef()] = [];
                }
                $segments[$segment->getRef()][$segment->getFlavor()] = $segment;

                return $segments;
            }, []);

            ksort($segments);
            $frameList = array_values(array_map(function (array $segment) {

                if (xoctConf::getConfig(xoctConf::F_USE_HIGH_LOW_RES_SEGMENT_PREVIEWS)) {
                    /**
                     * @var xoctAttachment[] $segment
                     */
                    $high = $segment[Metadata::FLAVOR_PRESENTATION_SEGMENT_PREVIEW_HIGHRES];
                    $low = $segment[Metadata::FLAVOR_PRESENTATION_SEGMENT_PREVIEW_LOWRES];
                    if ($high === null || $low === null) {
                        $high = $segment[Metadata::FLAVOR_PRESENTER_SEGMENT_PREVIEW_HIGHRES];
                        $low = $segment[Metadata::FLAVOR_PRESENTER_SEGMENT_PREVIEW_LOWRES];
                    }

                    $time = substr($high->getRef(), strpos($high->getRef(), ";time=") + 7, 8);
                    $time = new DateTime("1970-01-01 $time", new DateTimeZone("UTC"));
                    $time = $time->getTimestamp();

                    $high_url = $high->getUrl();
                    $low_url = $low->getUrl();
                    if (xoctConf::getConfig(xoctConf::F_SIGN_THUMBNAIL_LINKS)) {
                        $high_url = xoctSecureLink::signThumbnail($high_url);
                        $low_url = xoctSecureLink::signThumbnail($low_url);
                    }

                    return [
                        "id"       => "frame_" . $time,
                        "mimetype" => $high->getMediatype(),
                        "time"     => $time,
                        "url"      => $high_url,
                        "thumb"    => $low_url
                    ];
                } else {
                    $preview = $segment[Metadata::FLAVOR_PRESENTATION_SEGMENT_PREVIEW];

                    if ($preview === null) {
                        $preview = $segment[Metadata::FLAVOR_PRESENTER_SEGMENT_PREVIEW];
                    }

                    $time = substr($preview->getRef(), strpos($preview->getRef(), ";time=") + 7, 8);
                    $time = new DateTime("1970-01-01 $time", new DateTimeZone("UTC"));
                    $time = $time->getTimestamp();

                    $url = $preview->getUrl();
                    if (xoctConf::getConfig(xoctConf::F_SIGN_THUMBNAIL_LINKS)) {
                        $url = xoctSecureLink::signThumbnail($url);
                    }

                    return [
                        "id"       => "frame_" . $time,
                        "mimetype" => $preview->getMediatype(),
                        "time"     => $time,
                        "url"      => $url,
                        "thumb"    => $url
                    ];
                }
            }, $segments));
        }

        return $frameList;
    }
}
