<?php
defined('ROOT_PATH') or define('ROOT_PATH', '../');
defined('INCLUDE_PATH') or define('INCLUDE_PATH',     ROOT_PATH . 'includes/');

require_once INCLUDE_PATH . 'define.php';
defined('_PHP_CONGES') or die('Restricted access');
require_once INCLUDE_PATH .'session.php';

$config = new \App\Libraries\Configuration(\includes\SQL::singleton());

// verif des droits du user à afficher la page
verif_droits_user("is_hr");


/*************************************/
// recup des parametres reçus :
// SERVER
$PHP_SELF = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL);
// GET / POST
$onglet = getpost_variable('onglet', "page_principale");


/*********************************/
/*   COMPOSITION DES ONGLETS...  */
/*********************************/

$onglets = array();


$onglets['page_principale'] = _('resp_menu_button_retour_main');
$onglets['hr-group'] = _('admin_onglet_gestion_groupe');

if($config->canUserSaisieDemande()) {
    $onglets['traitement_demandes'] = _('resp_menu_button_traite_demande');
}

    $onglets['ajout_conges'] = _('resp_ajout_conges_titre');
    $onglets['jours_chomes'] = _('admin_button_jours_chomes_1');
    $onglets['jours_fermeture'] = _('divers_fermeture');
    $onglets['cloture_year'] = _('divers_fermeture');



$onglets['cloture_year'] = _('resp_cloture_exercice_titre');
$onglets['liste_planning'] = _('hr_liste_planning');
//$onglets['ajout_planning'] = _('hr_ajout_planning');

if (!isset($onglets[ $onglet ]) && !in_array($onglet, ['traite_user', 'modif_planning', 'ajout_planning', 'ajout_user', 'modif_user','suppr_user','liste_groupe','modif_groupe','ajout_groupe','suppr_groupe', 'jours_fermeture', 'cloture_year'], true)) {
    $onglet = 'page_principale';
}

/*********************************/
/*   COMPOSITION DU HEADER...    */
/*********************************/

header_menu('', 'Libertempo : '._('resp_menu_button_mode_hr'));


/*********************************/
/*   AFFICHAGE DE L'ONGLET ...    */
/*********************************/


/**
 * initialisation des tableaux des types de conges/absences  
**/
// recup du tableau des types de conges (seulement les conges)
$tab_type_cong=recup_tableau_types_conges();

// recup du tableau des types de conges exceptionnels (seulement les conges exceptionnels)
$tab_type_conges_exceptionnels=recup_tableau_types_conges_exceptionnels();

echo '<div class="'.$onglet.' wrapper" id="main-content">';
    require_once ROOT_PATH . 'hr/hr_'.$onglet.'.php';
echo '</div>';

/*********************************/
/*   AFFICHAGE DU BOTTOM ...   */
/*********************************/

bottom();
