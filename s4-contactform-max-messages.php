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

    private $_COUNTFIELDKEY = 's4u_cfmax_field';

    private $_FormOnThisPageId = 0;

    private $_ConditionalAmountField = false;

    private $_ShowConditionalMessage = false;

    function __construct() {
        add_filter( 'the_content', array($this, 'Loaded') );

        // a form MUST have at least one text-field
        add_filter( 'wpcf7_validate_text', array($this, 'SubmitValidate'), 999, 2 );
        add_filter( 'wpcf7_validate_text*', array($this, 'SubmitValidate'), 999, 2 );
        
    }

    function SetFormOnPage($id) {
        $this->_FormOnThisPageId = $id;
    }

    private function GetMessage() {
        if ($this->_ConditionalAmountField) {
            return "Het is niet meer mogelijk om dit aantal personen in te schrijven.";
        }
        else{
            return "Het is niet meer mogelijk te reageren/in te schrijven.";
        }
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
                        $maxReached = $this->HasReachedMaxMessages(false);
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
        global $_POST;
        if (isset($_GET['rest_route'])) {
            $idCheck = preg_match_all('/\/([0-9]+)\//', $_GET['rest_route'], $idmatch);
            if (is_array($idmatch)) {
                if (count($idmatch >= 2)) {
                    $this->_ShowConditionalMessage = true;
                    $this->SetFormOnPage(intval($idmatch[1][0]));  
                }
            }
        }
        else if (isset($_POST['_wpcf7'])) {
            $cfId = intval($_POST['_wpcf7']);
            if ($cfId > 0) {
                $this->_ShowConditionalMessage = true;
                $this->SetFormOnPage($cfId);
            }
        }
        $maxReached = $this->HasReachedMaxMessages(true);
        if ($maxReached) {
            $result->invalidate( $tag, $this->GetMessage() );
        }
        return $result;
    }

    private function HasReachedMaxMessages($submitValidation) {
        $result = false;
        if ($this->_FormOnThisPageId == 0) return false;

        if (class_exists(WPCF7_ContactForm) == false) return false;
        if (class_exists(Flamingo_Inbound_Message) == false) return false;

        $dummyForm = WPCF7_ContactForm::get_instance($this->_FormOnThisPageId);
        $dummyItems = $dummyForm->additional_setting('s4u_max_reactions');
        if (count($dummyItems) > 0) {
            $maxReactions = intval($dummyItems[0]);
            $reactionCount = $this->GetReactionCount($this->_FormOnThisPageId, $submitValidation);
            if ($submitValidation) {
                $reactionCount += $this->GetReactionCountOfThisSubmit($this->_FormOnThisPageId);
                if ($reactionCount > $maxReactions) {
                    $result = true;
                }
            }
            else{
                if ($reactionCount >= $maxReactions) {
                    $result = true;
                }
            }
        }
        return $result;
    }
  
    private function GetReactionCount($form, $submitValidation) {
        $reactionCount = 0;
        $dummyForm = WPCF7_ContactForm::get_instance($form);

        $fieldForCounting = get_post_meta( $form, $this->_COUNTFIELDKEY, true );
        $args = array('channel' => $dummyForm->name(), 'post_status' => 'publish');
        $reactions = Flamingo_Inbound_Message::find( $args );
        if (is_array($reactions)) {
            if ($fieldForCounting == '') {
                $reactionCount = count($reactions);
            }
            else {
                if ($this->_ShowConditionalMessage) {
                    $this->_ConditionalAmountField = true;
                }
                for ($a=0; $a < count($reactions); $a++) {
                    $count = intval(get_post_meta( $reactions[$a]->id, '_field_'.$fieldForCounting, true ));
                    if ($count <= 0) $count = 1;
                    $reactionCount += $count;                        
                }
            }
        }
        return $reactionCount;
    }

    private function GetReactionCountOfThisSubmit($form) {
        $reactionCount = 0;
        $dummyForm = WPCF7_ContactForm::get_instance($form);

        $fieldForCounting = get_post_meta( $form, $this->_COUNTFIELDKEY, true );
        if ($fieldForCounting == '') {
            $reactionCount++;
        }
        else {
            global $_POST;
            if (isset($_POST[$fieldForCounting])) {
                $count = intval($_POST[$fieldForCounting]);
                if ($count <= 0) $count = 1;
                $reactionCount += $count;
            }     
        }   
        return $reactionCount;
    }    

    public function SetCountField($form, $field) {
        $formToUpdate = intval($form);
        $contactForm = get_posts(array('page_id' => $formToUpdate, 'post_type' => 'wpcf7_contact_form'));
        if (count($contactForm) == 1) {
            $dummyForm = WPCF7_ContactForm::get_instance($formToUpdate);
            $formTags = $dummyForm->scan_form_tags();
            $saveValue = '';
            for ($z=0; $z < count($formTags); $z++) {
                if ( $formTags[$z]->name == $field ) {
                    $saveValue = $field;
                }
            }
            update_post_meta($formToUpdate, $this->_COUNTFIELDKEY, $saveValue);            
        }
    }

    public function show_admin_settings() {
        if (is_admin() == false) {
            exit;
        }

        if (isset($_POST['admin_action'])) {
            if ($_POST['admin_action'] == 's4u_cf_max_setfield') {
                $this->setCountField($_POST['form_id'], $_POST['field']);
            }
        }
?>
<h1>Configuratie</h1>
<p>Door bij een ContactForm 7 formulier op het tabblad "Additional Settings" de waarde<br/>
<blockquote><b><cite>s4u_max_reactions:1</cite></b></blockquote> toe te voegen, zorg je dat er maximaal 1 reactie/aanmelding uitgevoerd kan worden.<br/>
Door de waarde aan te passen kun je zelf het maximum bepalen.<br/>
<br/>
Elk ingezonden formulier geldt als <b>1</b> reactie/aanmelding.<br/>
Maar als je nu in &eacute;&eacute;n aanmelding meerdere personen kunt aanmelden?<br/>
Dan kun je hier het veld selecteren en wordt dat gebruikt om het totaal te berekenen.
</p>
<?php
$contactForms = get_posts(array('nopaging' => true, 'post_type' => 'wpcf7_contact_form'));
for ($m=0; $m < count($contactForms); $m++) {
?><hr/><form method="post"><input type="hidden" name="admin_action" value="s4u_cf_max_setfield"/><input type="hidden" name="form_id" value="<?php echo $contactForms[$m]->ID;?>" />
<?php
    echo "<h2><b>" . $contactForms[$m]->post_title . "</b></h2><br/>";
    $dummyForm = WPCF7_ContactForm::get_instance($contactForms[$m]->ID);
    $maximumAmount = $dummyForm->additional_setting('s4u_max_reactions');
    if (count($maximumAmount) == 0) {
        echo "Nog geen maximum ingesteld.<br/>";
    }
    else {
        echo "Maximum ingesteld op: ".$maximumAmount[0]." reactie(s)/aanmelding(en).<br/>";
        echo "Reeds ontvangen aanmeldingen: " . $this->GetReactionCount($contactForms[$m]->ID, false) . "<br/>";
    }
    $formTags = $dummyForm->scan_form_tags();
    if (count($formTags) == 0) {
        echo "Geen velden op dit formulier toegevoegd.<br/>Bij opslaan wordt het aantal inzendingen geteld en dus NIET een veld.<br/>";
    }
    else{
        $currentValue = get_post_meta( $contactForms[$m]->ID, $this->_COUNTFIELDKEY, true );
?><br/><select name="field">
<option value="">Standaard: tel aantal inzendingen</option>
<?php 
    for ($z=0; $z < count($formTags); $z++) {
        $name = $formTags[$z]->name;
        if ($name == '') continue;
        $sel = ($name == $currentValue ? ' selected="selected" ' : '');        
?><option value="<?php echo $name;?>" <?php echo $sel;?>>Veld: <?php echo $name;?></option><?php        
    }
?>
</select>
<?php
    }
?><input type="submit" value="Opslaan" />
</form>
<hr/>
<?php
}

    }    

    function add_admin_menu_action() {
        add_options_page( 'Maximum aantal per formulier', 'Contactform Max Messages', 'administrator', __FILE__, array($this, 'show_admin_settings'), 1 );
    }

}

$dummy = new ContactForm7MaxMessages();
add_action('admin_menu', array($dummy, 'add_admin_menu_action'));
?>