<?php

class NP_TinyMCE4 extends NucleusPlugin {

    private $memory_bconvertbreaks;
    private $langs;

    function getName()              {return 'NP_TinyMCE4';}
    function getVersion()           {return '0.1';}
    function getDescription()       {return $this->_('description');}
    function getAuthor()            {return 'yamamoto';}
    function getMinNucleusVersion() {return 370;}

    function getEventList() {
        return explode(',', 'AdminPrePageHead,BookmarkletExtraHead,PreSendContentType,PreAddItem,PreUpdateItem,PostAddItem,PostUpdateItem,PrepareItemForEdit');
    }

    function install() {
        require($this->getDirectory() . 'inc/install.php');
    }

    function init() {
        global $CONF;
        $adminurl = parse_url($CONF['AdminURL']);
        if(strpos($_SERVER['SCRIPT_NAME'], $adminurl['path'])!==0) return;
        
        $this->langs = include_once($this->getDirectory() . 'langs/english-utf8.php');
        if(strpos(getLanguageName(),'english')!==0) {
            $this->langs = array_merge($this->langs, $this->get_lang_entries());
        }
    }
    
    function event_BookmarkletExtraHead(&$data) {
        
        if (!$this->get_use_mce_status()) return;
        
        $this->_addExtraHead($data['extrahead']);
    }

    function event_AdminPrePageHead(&$data) {
        global $member, $blogid, $manager;
        
        $action = $data['action'];
        
        $useEditor = false;
        if (strpos($action,'plugin') !== false)
        {
            $info['editor']    = 'TinyMCE4';
            $info['action']    = $action;
            $info['useEditor'] = &$useEditor;
            $manager->notify('EditorAdminPrePageHead', $info);
        } 

        if (!in_array($action, ['createitem','itemedit']) && !$useEditor) return;
        
        $this->_addExtraHead($data['extrahead']);
    }

    function event_PreSendContentType(&$data) {
        
        if (!$this->get_use_mce_status()) return;
        
        $pageTypes = ['bookmarklet-add','bookmarklet-edit','admin-createitem','admin-itemedit'];
        if (!in_array($data['pageType'], $pageTypes))        return;
        if ($data['contentType'] != 'application/xhtml+xml') return;
        
        $data['contentType'] = 'text/html';
    }

    function event_PreAddItem(&$data) {
        $this->_suspendConvertBreaks($data);
        $this->_recoverTags($data);
    }
    
    function event_PreUpdateItem(&$data) {
        $this->_suspendConvertBreaks($data);
        $this->_recoverTags($data);
    }
    
    function event_PostAddItem(&$data) {
        $this->_restoreConvertBreaks($data);
    }
    
    function event_PostUpdateItem(&$data) {
        $this->_restoreConvertBreaks($data);
    }

    function event_PrepareItemForEdit($data) {
        
        if (!$this->get_use_mce_status()) return;
        
        $src  = array('<%',     '%>',    '<!%',     '%!>');
        $dist = array('@&lt;%', '%&gt;@', '@&lt;!%', '%!&gt@;');
        $data['item']['body'] = str_replace($src, $dist, $data['item']['body']);
        $data['item']['more'] = str_replace($src, $dist, $data['item']['more']);
    }

    private function _addExtraHead(&$extrahead) {
        global $CONF;
        
        if (!$this->get_use_mce_status()) return;
        
        $CONF['DisableJsTools'] = 1; // overrule simple global settings
        $_ = [];
        $_[] = $this->render('<script src="<%_(plugin_url)%>tinymce/tinymce.min.js"></script>', ['plugin_url'=>$this->getAdminURL()]);
        if (is_file($this->getDirectory().'inc/mce_init.js'))
        {
            $ph['mce_options'] = file_get_contents($this->getDirectory().'inc/mce_init.js');
            $ph['lang_code']   = $this->get_lang_code();
            $_[] = $this->render("<script><%_(mce_options)%></script>",$ph);
        }
        $extrahead .= join("\n",$_);
    }
    
    private function get_use_mce_status() {
        global $itemid, $member;
        
        if ($this->getItemOption($itemid,       'use_tinymce')!='yes') return false;
        if ($this->getMemberOption($member->id, 'use_tinymce')!='yes') return false;
        
        return true;
    }
    
    private function _suspendConvertBreaks(&$data) {
        global $manager;
        
        if (!$this->get_use_mce_status()) return;
        
        $blogObject = & $manager->getBlog($data['blog']->blogid);
        
        $this->memory_bconvertbreaks = $blogObject->getSetting('bconvertbreaks');
        
        if (!$this->memory_bconvertbreaks) return;
        
        $data['body'] = removeBreaks($data['body']);
        $data['more'] = removeBreaks($data['more']);
        $blogObject->setConvertBreaks(false);
        $blogObject->writeSettings();
    }
    
    private function _restoreConvertBreaks(&$data) {
        global $manager;
        
        if (!$this->get_use_mce_status()) return;
        
        if (!$this->memory_bconvertbreaks) return;
        
        $itemid = intval($data['itemid']);
        $blogObject = & $manager->getBlog(getBlogIDFromItemID($itemid));
        
        $blogObject->setConvertBreaks(true);
        $blogObject->writeSettings();
    }

    private function _recoverTags(&$item) {
        
        if (!$this->get_use_mce_status()) return;
        
        $item['body'] = preg_replace('/@&lt;%(.+?)%&gt;@/', '<%$1%>', $item['body']);
        $item['body'] = preg_replace('/@&lt;\!%(.+?)%!&gt;@/', '<!%$1%!>', $item['body']);
        $item['body'] = preg_replace('@<br />(.+?)@', "<br />\n$1", $item['body']);
        $item['more'] = preg_replace('/@&lt;%(.+?)%&gt;@/', '<%$1%>', $item['more']);
        $item['more'] = preg_replace('/@&lt;\!%(.+?)%!&gt;@/', '<!%$1%!>', $item['more']);
        $item['more'] = preg_replace('@<br />(.+?)@', "<br />\n$1", $item['more']);
    }
    
    private function render($tpl, $ph, $left='<%_(', $right=')%>') {
        foreach($ph as $k=>$v) {
            $k = $left . $k . $right;
            $tpl = str_replace($k,$v,$tpl);
        }
        return $tpl;
    }
    
     private function get_lang_entries() {
        $lang_file_path  = $this->getDirectory() . 'langs/' . getLanguageName() . '.php';
        if (is_file($lang_file_path))
        {
             return include_once($lang_file_path);
        }
        return array();
    }
    
    private function get_lang_code() {
        $lang_name = getLanguageName();
        if    (strpos($lang_name,'japanese')===0) return 'ja';
        elseif(strpos($lang_name,'french')  ===0) return 'fr_FR';
        else                                      return 'en';
    }
    
    private function _($str='') {
        
        if(isset($this->langs[$str])) return $this->langs[$str];
        
        return $str;
    }
}