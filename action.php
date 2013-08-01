<?php
/**
 * registers users by means of a confirmation link
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Myron Turner<turnermm02@shaw.ca>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_preregister extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    private $metaFn;
    private $captcha;
    
    function register(&$controller){
            $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'BEFORE', $this, 'update_register_form');
            $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'allow_preregister_check');
            $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'process_preregister_check');     
            $controller->register_hook('TPL_METAHEADER_OUTPUT', 'AFTER', $this, 'metaheaders_after');
     }
    
    function __construct() {       
       $metafile= 'preregister:db';
       $this->metaFn = metaFN($metafile,'.ser');
       $this->check_captcha_selection();       
    }
    function metaheaders_after (&$event, $param) {   
         global $ACT;
         if($ACT !== 'register') return;        
         
         if($this->captcha == 'none' || $this->captcha == 'builtin')  { 
            ptln( "\n<style type='text/css'>\n   /*<![CDATA[*/");
            ptln("#plugin__captcha_wrapper{ display:none; }\n   /*]]>*/\n</style>");
         }   

    }    
    
        
   function allow_preregister_check(&$event, $param) {
    $act = $this->_act_clean($event->data);    
    if($act != 'preregistercheck') return; 
    $event->preventDefault();
  }
 
    function process_preregister_check(&$event, $param) {
         global $ACT;
      
         if($ACT != 'preregistercheck') return; 
         if($_GET && $_GET['prereg']) {
             echo $this->getLang('registering') . $_GET['prereg'];
             $this->process_registration($_GET['prereg']);
             $event->preventDefault();
             return;
         }

        $event->preventDefault();   
      
          if(!$_POST['login']){
            msg('missing login: please fill out all fields');
            return;
          }
          else if(!$_POST['fullname']) {
             msg('missing Real Name: please fill out all fields');
            return;
          }

        if($this->captcha =='captcha') {         
            $captcha = $this->loadHelper('captcha', true);
            if(!$captcha->check()) {
               return;
            }
        }
         
         if($this->is_user($_REQUEST['login']))  return;  // name already taken
         if($this->captcha == 'builtin') {
             $failed = false;
             if(!isset($_REQUEST['card'])) {
               echo '<h4>'. $this->getLang('cards_nomatch') . '</h4>';
               return;
             }
             foreach($_REQUEST['card'] as $card) {          
                 if(strpos($_REQUEST['sel'],$card) === false) {
                     $failed = true;
                     break;                
                 }
              }
             if($failed) {    
                 echo '<h4>'. $this->getLang('cards_nomatch') . '</h4>';
                 return;
            }
        }
        $t = time();
        $index = md5($t);
        $url = DOKU_URL . 'doku.php?' . $_REQUEST['id']. '&do=preregistercheck&prereg='. $index;    
        if($this->getConf('send_confirm')) {        
            $valid_email = true;
            if($this->send_link($_REQUEST['email'], $url,$valid_email) ) {
              echo $this->getLang('confirmation');
            }
            else if($valid_email) {
                echo $this->getLang('email_problem'); 
            }
        }
        else {
           echo "<a href='$url'>$url</a><br /><br />\n";
           echo $this->getLang('screen_confirm');
        }  
   
          $data = unserialize(io_readFile($this->metaFn,false)); 
          if(!$data) $data = array();          
          $data[$index] = $_POST;
          $data[$index]['savetime'] = $t;
          io_saveFile($this->metaFn,serialize($data));
    }
  
    function update_register_form(&$event, $param) {    
        if($_SERVER['REMOTE_USER']){
            return;
        }
      
        $event->data->_hidden['save'] = 0;
        $event->data->_hidden['do'] = 'preregistercheck';
 
        for($i=0; $i <count($event->data->_content); $i++) {
            if(isset($event->data->_content[$i]['type']) && $event->data->_content[$i]['type'] == 'submit') 
            {   
                $event->data->_content[$i]['value'] = 'Submit';
                break; 
            }
        }    
        $pos = $event->data->findElementByAttribute('type','submit');       
        if(!$pos) return; // no button -> source view mode
        if($this->captcha == 'builtin') {        
            $cards = $this-> get_cards();
            $sel ="";
            $out = $this->format_cards($cards,$sel);                
            $event->data->_hidden['sel'] = implode("",$sel);      
           $event->data->insertElement($pos++,$out);
        }   
    }
    

    function process_registration($index) {

           $data = unserialize(io_readFile($this->metaFn,false)); 
           if(!isset($data[$index])) {
              msg($this->getLang('old_confirmation'));
              return;
           }
           $post = $data[$index];
           $post['save'] = 1;
           $_POST= array_merge($post, array());
           if(register()) {
              unset($data[$index]);
              io_saveFile($this->metaFn,serialize($data));
           }
          
    }

 
    function check_captcha_selection() {
       $list = plugin_list();
       $this->captcha = $this->getConf('captcha');     
       if(!in_array('captcha', $list)) {
           if(preg_match("/captcha/", $this->captcha)) {                           
               $this->captcha = 'builtin';
           }
           return;
       }    
       if($this->captcha == 'none' || $this->captcha == 'builtin')  { 
           return;
       }
      if(plugin_isdisabled('captcha')) {
          $this->captcha = 'builtin';
          return;             
      }
      $this->captcha ='captcha';     
       
    }
    
    /**
     * Pre-Sanitize the action command
     *
     * Similar to act_clean in action.php but simplified and without
     * error messages
     */
    function _act_clean($act){
         // check if the action was given as array key
         if(is_array($act)){
           list($act) = array_keys($act);
         }

         //remove all bad chars
         $act = strtolower($act);
         $act = preg_replace('/[^a-z_]+/','',$act);

         return $act;
     }
     
    function format_cards($cards,&$sel) {
        $sel = array_slice($cards,0,3);
        shuffle($cards);
        $new_row = (int)(count($cards)/2);
        $out = $sel[0] . '&nbsp;&nbsp;' . $sel[1] . '&nbsp;&nbsp;' . $sel[2] . '<br />';
        $out = str_replace(array('H','S','D','C'),array('&#9829;','&#9824;','&#9830;','&#9827;'),$out);
        $out = "Check off the matching cards<br />" . $out;
        $out .= '<center><table cellspacing="2"><tr>';
        $i=0;
        foreach($cards as $card) {
            $i++;
            $name = 'card[]'; 
            
            $out .= '<td>' . str_replace(array('H','S','D','C'),array('&#9829;','&#9824;','&#9830;','&#9827;'),$card)
                    . " <input type = 'checkbox' name = '$name' value = '$card' /></td>"; 
            if($i==$new_row) $out .='</tr><tr>';        
        }
        $out .= '</tr></table></center>';
        return $out;
   }    
   
    function get_cards() {       
         for($i=1; $i<14; $i++) {
            $c = $i;
            if($i == 1) {
              $c='A';
             }
            if($i == 11) {
              $c='J';
            }            
             if($c == 12) {
              $c='Q';
            }            
            if($i == 13) {
              $c='K';
            }            
            $hearts[] = $c . "H";
            $clubs[] = $c. "C";
            $diamonds[] = $c ."D";
            $spades[] =  $c."S";
         }
     $deck = array_merge($hearts,$clubs, $diamonds, $spades); 
     shuffle($deck);
      return array_slice($deck,0,10);
    }
    
    
    function send_link($email, $url, &$valid_email) {  
        
        if(!mail_isvalid($email)) {
             msg($this->getLang('bad_email') . $email);
             $valid_email = false;
             return false; 
        } 
     
        global $conf;
        
        $text = $this->getLang('email_confirm')  . "\n\n";
        $text .= $url;
        $text .= "\n\n";      
        $subject =$this->getLang('subject_confirm');
        return mail_send($email, $subject . $conf['title'],$text, $conf['mailfrom']);
        
}

function is_user($uname) {
    global $config_cascade;
    $authusers = $config_cascade['plainauth.users']['default'];
    if(!is_readable($authusers)) return false;
   
    $users = file($authusers);
    $uname = utf8_strtolower($uname);
    foreach($users as $line) {
        $line = trim($line);
        if($line{0} == '#') continue;
        list($user,$rest) = preg_split('/:/',$line,2);
        if(!trim($user)) continue;
        if($uname == $user) {
           msg($this->getLang('uid_inuse') . $user,-1);
           return true;
        }      
    }
    return false;
 }   
 
    function write_debug($what, $toscreen=true, $tofile=false) {
    
        if(is_array($what)) {
            $what = print_r($what,true);
        }
        if($toscreen) {
           return "<pre>$what</pre>" ;
        }
        if(!$tofile) {
           return "";
        }        


       $handle=fopen('preregister.txt','a');
        fwrite($handle, "$what\n");
        fclose($handle);
     }   
}

