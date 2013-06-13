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

class VideoController extends MediaController
{
    protected $type = 'video';
    function getFlowplayerParams(ResourceAbstractFile $media)
    {
        $localConfig = array();

        if (!$media->config) {

        } elseif (substr($media->config, 0,6) == 'preset') {
            $presets = unserialize($this->getDi()->store->getBlob('flowplayer-presets'));
            $localConfig = $presets[$media->config]['config'];
        } else {
            $localConfig = unserialize($media->config);
        }

        $config = array_merge($this->getDi()->config->get('flowplayer', array()), $localConfig);

        $params = array (
            'height' => @$config['height'],
            'width' => @$config['width'],
            'clip' => array(
                    'autoPlay' => (isset($config['autoPlay']) && $config['autoPlay']) ? true : false,
                    'autoBuffering' => (isset($config['autoBuffering']) && $config['autoBuffering']) ? true : false,
                    'bufferLength' => isset($config['bufferLength']) ? $config['bufferLength'] : 3,
                    'scaling' => isset($config['scaling']) ? $config['scaling'] : 'scale'
                ),
        );

        return $params;
    }
}