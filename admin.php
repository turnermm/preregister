<?php
/**
 * 
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Myron Turner <turnermm02@shaw.ca>
 */

 
/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_preregister extends DokuWiki_Admin_Plugin {

    var $output = '';
    private $metaFn;
    
     function __construct() {       
       $metafile= 'preregister:db';
       $this->metaFn = metaFN($metafile,'.ser');
    }
    /**
     * handle user request
     */
    function handle() {
    
      if (!isset($_REQUEST['cmd'])) return;   // first time - nothing to do

      $this->output = 'invalid';
      
      if (!checkSecurityToken()) return;
      if (!is_array($_REQUEST['cmd'])) return;
      
      // verify valid values
      switch (key($_REQUEST['cmd'])) {
        case 'confirm' : 
        $this->prune_datafile($_REQUEST['del']) ;
         break;
        case 'secure' : 
        $this->secure_datafile() ;
        break;
      }      
   //  msg('<pre>' . print_r($_REQUEST['del'],true) . '</pre>');    
   
    }
 
    /**
     * output appropriate html
     */
    function html() {
    ptln('<a href="javascript:prereg_Inf.toggle();void 0"><span style="line-height: 175%">Toggle info</span></a><br />');
     ptln('<div id="prereg_info" style="border: 1px solid silver; padding:4px;">'.     $this->locale_xhtml('info') . '</div><br>' );
    
      
      ptln('<form action="'.wl($ID).'" method="post">');
      
      // output hidden values to ensure dokuwiki will return back to this plugin
      ptln('  <input type="hidden" name="do"   value="admin" />');
      ptln('  <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
      formSecurityToken();      
      ptln('  <input type="submit" name="cmd[confirm]"  value="'.$this->getLang('btn_confirm').'" />&nbsp;&nbsp;');
      ptln('  <input type="submit" name="cmd[secure]"  value="'.$this->getLang('btn_secure').'" />');
       ptln('<br /><br /><div>');
      echo $this->getConfirmList();    
      ptln('</div>');
      ptln('</form>');
      $this->js();  
    }
 
    function getConfirmList() {
        global $conf;
       
        $delete_time = $this->getConf('list_age'); 
        if(strpos($delete_time,'*') !== false) {
           $elems = explode('*',$delete_time);           
           $delete_time = 1;
           foreach($elems as $n) {
               $delete_time *= $n; 
           }
        }
        
        $data = unserialize(io_readFile($this->metaFn,false)); 
        $hidden = array();
        $result = "<table cellspacing='2'><th>login</th><th>email</th><th>name</th><th>save time</th><th>age</th>";
        $current_time = time();
        foreach($data as $index=>$entry) {
            $age = $current_time - $entry['savetime'];
            if($age >= $delete_time) {            
                $hidden[] = $index;
                $hours = round(($age/3600),2); 
                if($hours >= 24) {
                    $hours = round($hours/24,2);
                    $hours .= ' day(s)';
                }
                else $hours .= ' hours'; 
                $result .= '<tr><td>'. $entry['login'] . '</td><td>' . $entry['email'] . '</td><td>'  . $entry['fullname'] 
                . '</td><td>' . strftime($conf['dformat'],$entry['savetime']) . '</td><td>' . $hours .  "</td></tr>\n";
              }
        }
        $result .= '</table>';
        foreach($hidden as $del) {
            $result .= "\n<input type='hidden' name='del[]'  value = '$del' />";           
        }
        return $result ."\n";      
    }
    
    function secure_datafile() {
         $perm = substr(sprintf('%o', fileperms($this->metaFn )), -4);         
         if(preg_match('/\d\d(\d)/',$perm,$matches)) {   
            if($matches[1] > 0) {
                 msg("Data file is currently accessible to all: $perm");
                 if(chmod($this->metaFn ,0600)) { 
                    msg("Succesfully change permissions to: 0600");
                 }
                 else  msg("Unable to change permissions to: 0600");
             }
         }
    }
    function prune_datafile($which) {
        $data = unserialize(io_readFile($this->metaFn,false)); 
        foreach($data as $index=>$entry) {
            if(in_array($index,$which)) {
                 unset($data[$index]);
            }            
        }        
 
        io_saveFile($this->metaFn,serialize($data));
    }
    function js() {
echo <<<SCRIPT
<script type="text/javascript">
    //<![CDATA[    
var prereg_Inf = {
dom_style: document.getElementById('prereg_info').style,
open: function() { this.dom_style.display = 'block'; },
close: function() { this.dom_style.display = "none"; },
toggle: function() { 
if(this.is_open) { this.close(); this.is_open=false; return; }
this.open(); this.is_open=true;
},
is_open: true,
};    
    //]]>
 </script>
SCRIPT;

    }
}