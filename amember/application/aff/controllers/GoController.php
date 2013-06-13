<?php 

class Aff_GoController extends Am_Controller
{
    /** @var User */
    protected $aff;
    /** @var Banner */
    protected $banner;
    
    /** @return User|null */
    function findAff()
    {
        $id = $this->getFiltered('r');
        if ($id > 0)
        {
            $aff = $this->getDi()->userTable->load($id, false);
            if ($aff) return $aff;
        }
        if (strlen($id))
        {
            $aff = $this->getDi()->userTable->findFirstByLogin($id);
            if ($aff) return $aff;
        }
        return null;
    }
    function findAm3Aff()
    {
        $id = $this->getFiltered('r');
        if ($id > 0)
        {
            $newid = $this->getDi()->getDbService()->selectCell("SELECT id from ?_data 
                where `key`='am3:id' AND `table`='user' and value=?",$id);
            if ($newid > 0)
            {
                $aff = $this->getDi()->userTable->load($newid, false);
                if ($aff) return $aff;
            }
        }
        return null;
    }
    function findUrl()
    {
        $link = $this->getInt('i');
        if ($link > 0 )
        {
            $this->banner = $this->getDi()->affBannerTable->load($link, false);
            return $this->banner->url;
        } else {
            return $this->getDi()->config->get('aff.general_link_url', null);
        }
    }
    function indexAction()
    {
        $this->aff = $this->findAff();
        $this->link = $this->findUrl();
        /// log click
        if ($this->aff)
        {
            $aff_click_id = $this->getDi()->affClickTable->log($this->aff, $this->banner);
            $this->getModule()->setCookie($this->aff, $this->banner ? $this->banner : null, $aff_click_id);
        }
        $this->_redirect($this->link ? $this->link : '/', array('prependBase'=>false));
    }
    function findAm3Url()
    {
        $r = $this->getFiltered('i');
        $r_id = substr($r,1);
        $r_type = substr($r,0,1);
        if ($r_id > 0 && $r_type)
        {
            $url = $this->getDi()->db->selectCell("SELECT url from ?_aff3_banner where banner_link_id=? and type=?",$r_id,$r_type);
            return ($url) ? $url : $this->getDi()->config->get('aff.general_link_url', null);
        } else {
            return $this->getDi()->config->get('aff.general_link_url', null);
        }
    }
    function am3goAction()
    {
        $this->aff = $this->findAm3Aff();
        $this->link = $this->findAm3Url();
        /// log click
        if ($this->aff)
        {
            $aff_click_id = $this->getDi()->affClickTable->log($this->aff, $this->banner);
            $this->getModule()->setCookie($this->aff, $this->banner ? $this->banner : null, $aff_click_id);
        }
        $this->_redirect($this->link ? $this->link : '/', array('prependBase'=>false));
    }
}