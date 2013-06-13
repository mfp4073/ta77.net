<?php

/*
 *   Members page. Used to renew subscription.
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Member display page
 *    FileName $RCSfile$
 *    Release: 4.2.17 ($Revision: 5371 $)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

include_once 'MediaController.php';

class AudioController extends MediaController
{
    protected $type = 'audio';
    function getFlowplayerParams(ResourceAbstractFile $media)
    {
        $params = array(
            'height' => 30,
            'width' => 500,
            'plugins' => array(
                'controls' => array(
                    'fullscreen' => false,
                    'height' => 30,
                    'autoHide' => false
                ),
                'audio' => array(
                    'url' => REL_ROOT_URL . '/application/default/views/public/js/flowplayer/flowplayer.audio.swf',
                )
            ),
            'clip' => array(
                'autoPlay' => false,
                'provider' => 'audio'
            )
        );

        return $params;
    }

}