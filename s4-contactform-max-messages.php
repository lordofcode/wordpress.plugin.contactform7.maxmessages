<?php
/**
 * @package S4U Contactform Max Messages
 * @version 1.0.0
 */
/*
Plugin Name: S4U Contactform Max Messages
Plugin URI: https://solution4u.nl/wordpress-plug-in-contactform7-maximum-aantal-reacties/
Description: Limit the amount of reactions / subscriptions on a contact-form 7 form
Author: ing. Dirk Hornstra
Version: 1.0.0
Author URI: https://solution4u.nl/
*/
class ContactForm7MaxMessages {

    private $_FormOnThisPageId = 0;

    function __construct() {
        add_filter( 'the_content', array($this, 'Loaded') );

        // a form MUST have at least one text-field
        add_filter( 'wpcf7_validate_text', array($this, 'SubmitValidate'), 10, 2 );
    }

    function SetFormOnPage($id) {
        $this->_FormOnThisPageId = $id;
    }

    private function GetMessage() {
        return "Het is niet meer mogelijk te reageren/in te schrijven.";
    }
    
    public function Loaded($content) {
        $tag = 'contact-form-7';
        if (has_shortcode($content, $tag)) {
            preg_match_all( '/' . get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER );
            for ($k=0; $k < count($matches); $k++) {
                if (is_array($matches[$k])) {
                    $isContactForm=false;
                    $idOfContactForm=0;
                    $fullMatch = $matches[$k];
                    for ($m=0; $m < count($matches[$k]); $m++) {
                        if ($matches[$k][$m] == $tag) {
                            $isContactForm = true;
                        }
                        else {
                            $idCheck = preg_match_all('/id=([\'"]*)([0-9]+)([\'"]*)/', $matches[$k][$m], $idmatch);
                            if ($idCheck > 0) {
                                preg_match('/([0-9]+)/', $idmatch[0][0], $idOfFormTemp);
                                if (intval($idOfFormTemp[0]) > 0) {
                                    $this->SetFormOnPage(intval($idOfFormTemp[0]));
                                }
                            }
                        }
                    }
                    if (($isContactForm) && ($this->_FormOnThisPageId > 0)) {
                        $maxReached = $this->HasReachedMaxMessages();
                        if ($maxReached) {
                            $content = str_replace($fullMatch, $this->GetMessage(), $content);
                        }
                    }
                }
            }
        }
        return $content;
    }

    public function SubmitValidate($result, $tag) {
        global $_GET;
        if (isset($_GET['rest_route'])) {
            $idCheck = preg_match_all('/\/([0-9]+)\//', $_GET['rest_route'], $idmatch);
            if (is_array($idmatch)) {
                if (count($idmatch >= 2)) {
                    $this->SetFormOnPage(intval($idmatch[1][0]));  
                }
            }
        }
        $maxReached = $this->HasReachedMaxMessages();
        if ($maxReached) {
            $result->invalidate( $tag, $this->GetMessage() );
        }
        return $result;
    }

    private function HasReachedMaxMessages() {
        $result = false;
        if ($this->_FormOnThisPageId == 0) return false;

        if (class_exists(WPCF7_ContactForm) == false) return false;
        if (class_exists(Flamingo_Inbound_Message) == false) return false;

        $dummyForm = WPCF7_ContactForm::get_instance($this->_FormOnThisPageId);
        $dummyItems = $dummyForm->additional_setting('s4u_max_reactions');
        if (count($dummyItems) > 0) {
            $maxReactions = intval($dummyItems[0]);
            $reactionCount = 0;
            $args = array('channel' => $dummyForm->name(), 'post_status' => 'publish');
            $reactions = Flamingo_Inbound_Message::find( $args );
            if (is_array($reactions)) {
                $reactionCount = count($reactions);
            }
            if ($reactionCount >= $maxReactions) {
                $result = true;
            }
        }    
        return $result;
    }

}

$dummy = new ContactForm7MaxMessages();
?>