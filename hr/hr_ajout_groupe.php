<?php

defined( '_PHP_CONGES' ) or die( 'Restricted access' );
$gestionGroupes = new \App\ProtoControllers\Groupe\Gestion();

$idGroupe = NIL_INT;

/**
 *
 * Formulaire de selection des employés d'un groupe
 *
 * @param int $id
 * @return string
 */
function getFormChoixEmploye($idGroupe, $data = NULL)
{
    $table = new \App\Libraries\Structure\Table();
    $table->addClasses([
        'table',
        'table-hover',
        'table-condensed',
        'table-striped',
        'table-condensed',
    ]);

    $childTable = '<thead>';
    $childTable .= '<tr>';
    $childTable .= '<th></th>';
    $childTable .= '<th>' . _('divers_personne_maj_1') . '</th>';
    $childTable .= '<th>' . _('divers_login') . '</th>';
    $childTable .= '</tr>';
    $childTable .= '</thead>';
    $childTable .= '<tbody>';
    $i = true;
    foreach (getEmployes($idGroupe) as $login => $info) {
        $inputOption = '';

        if (isset($data)) {
            if (in_array($login, $data['responsables']) || in_array($login, $data['grandResponsables'])) {
                $inputOption = 'disabled';
            } elseif (in_array($login, $data['employes'])) {
                $inputOption = 'checked';
            }
        } elseif (\App\ProtoControllers\Groupe::isResponsableGroupe($login, [$idGroupe], \includes\SQL::singleton())) {
            $inputOption = 'disabled';
        } elseif ($info['isDansGroupe']) {
            $inputOption = 'checked';
        }

        $childTable .= '<tr class="' . (($i) ? 'i' : 'p') . '">';
        $childTable .='<td class="histo"><input type="checkbox" id="Emp_' . $login . '" name="checkbox_group_users[' . $login . '] "' . $inputOption . '></td>';
        $childTable .= '<td class="histo">' . $info['nom'] . ' ' . $info['prenom'] . '</td>';
        $childTable .= '<td class="histo">' . $login . '</td>';
        $childTable .= '</tr>';
    }
    $childTable .= '</tbody>';
    $table->addChild($childTable);
    ob_start();
    $table->render();
    $return = ob_get_clean();

    return $return;
}

/**
 *
 * retournes les utilisateurs
 * si $idGroupe existe, marquage des employés du groupe
 *
 * @param int $idGroupe
 * @return array
 */
function getEmployes($idGroupe = NIL_INT)
{
    $employes = [];
    $idsUtilisateurs = \App\ProtoControllers\Utilisateur::getListId(true);
    foreach ($idsUtilisateurs as $login) {
        $donnees = \App\ProtoControllers\Utilisateur::getDonneesUtilisateur($login);
        $employes[$login] = [
            'nom' => $donnees['u_nom'],
            'prenom' => $donnees['u_prenom'],
            'login' => $donnees['u_login'],
            'isDansGroupe' => false
        ];
        if (NIL_INT != $idGroupe) {
            $employes[$login]['isDansGroupe'] = \App\ProtoControllers\Groupe\Utilisateur::isUtilisateurDansGroupe($login, $idGroupe, \includes\SQL::singleton());
        }
    }
    return $employes;
}

/**
 *
 * Formulaire de selection du responsable d'un groupe
 *
 * @param int $idGroupe
 * @return string
 */
function getFormChoixResponsable($idGroupe, $selectId, $data)
{
    $table = new \App\Libraries\Structure\Table();
    $table->addClasses([
        'table',
        'table-hover',
        'table-responsive',
        'table-condensed',
        'table-striped',
    ]);
    $childTable = '<thead>';

    $childTable .= '<tr>';
    $childTable .= '<th>&nbsp;</th>';
    $childTable .= '<th>' . _('divers_personne_maj_1') . '</th>';
    $childTable .= '<th>' . _('divers_login') . '</th>';
    $childTable .= '</tr>';
    $childTable .= '</thead>';
    $childTable .= '<tbody>';
    $i = true;
    foreach (getInfosResponsables($idGroupe) as $login => $info) {
        $inputOption = '';

        if (isset($data)) {
            if (in_array($login, $data['grandResponsables'])) {
                $inputOption = 'disabled';
            } elseif (in_array($login, $data['responsables'])) {
                $inputOption = 'checked';
            }
        } elseif ($info['isDansGroupe']) {
            $inputOption = 'checked';
        }

        $childTable .= '<tr class="' . (($i) ? 'i' : 'p') . '">';
        $childTable .='<td class="histo"><input type="checkbox" id="Resp_' . $login . '" name="checkbox_group_resps[' . $login . ']" onchange="disableCheckboxGroupe(this,\'' . $selectId . '\');" ' . $inputOption . '></td>';
        $childTable .= '<td class="histo">' . $info['nom'] . ' ' . $info['prenom'] . '</td>';
        $childTable .= '<td class="histo">' . $login . '</td>';
        $childTable .= '</tr>';
    }
    $childTable .= '</tbody>';
    $table->addChild($childTable);
    ob_start();
    $table->render();
    $return = ob_get_clean();

    return $return;
}

/**
 *
 * retournes les utilisateurs responsables
 * si $idGroupe existe, marquage des responsables du groupe
 *
 * @param int $idGroupe
 * @return array
 */
function getInfosResponsables($idGroupe = NIL_INT)
{
    $responsables = [];

    $infosResps = \App\ProtoControllers\Responsable::getInfosResponsables(\includes\SQL::singleton(),true);
    foreach ($infosResps as $infos) {
        $login = $infos['u_login'];
        $responsables[$login] = [
            'nom' => $infos['u_nom'],
            'prenom' => $infos['u_prenom'],
            'login' => $login,
            'isDansGroupe' => false
        ];

        if (NIL_INT !== $idGroupe) {
            $responsables[$login]['isDansGroupe'] = \App\ProtoControllers\Groupe::isResponsableGroupe($login, [$idGroupe], \includes\SQL::singleton());
        }
    }
    return $responsables;
}

/**
 *
 * Formulaire de selection des grands responsables d'un groupe
 *
 * @param int $idGroupe
 * @return string
 */
function getFormChoixGrandResponsable($idGroupe,$selectId, $data)
{
    $table = new \App\Libraries\Structure\Table();
    $table->addClasses([
        'table',
        'table-hover',
        'table-responsive',
        'table-condensed',
        'table-striped',
    ]);
    $childTable = '<thead>';

    $childTable .= '<tr>';
    $childTable .= '<th>&nbsp;</th>';
    $childTable .= '<th>' . _('divers_personne_maj_1') . '</th>';
    $childTable .= '<th>' . _('divers_login') . '</th>';
    $childTable .= '</tr>';
    $childTable .= '</thead>';
    $childTable .= '<tbody>';
    $i = true;
    foreach ($this->getGrandResponsables($idGroupe) as $login => $info) {
        $inputOption = '';

        if (isset($data)) {
            if (in_array($login, $data['grandResponsables'])) {
                $inputOption = 'checked';
            }
        } elseif ($info['isDansGroupe']) {
            $inputOption = 'checked';
        }

        $childTable .= '<tr class="' . (($i) ? 'i' : 'p') . '">';
        $childTable .='<td class="histo"><input type="checkbox" id="Gres_' . $login . '" name="checkbox_group_grand_resps[' . $login . ']" onchange="disableCheckboxGroupe(this,\'' . $selectId . '\');"' . $inputOption . '></td>';
        $childTable .= '<td class="histo">' . $info['nom'] . ' ' . $info['prenom'] . '</td>';
        $childTable .= '<td class="histo">' . $login . '</td>';
        $childTable .= '</tr>';
    }
    $childTable .= '</tbody>';
    $table->addChild($childTable);
    ob_start();
    $table->render();
    $return = ob_get_clean();

    return $return;
}

/**
 *
 * retournes les utilisateurs responsables
 * si $idGroupe existe, marquage des grands responsables du groupe
 *
 * @param int $idGroupe
 * @return array
 */
function getGrandResponsables($idGroupe = NIL_INT)
{
    $responsables = [];

    $infosResps = \App\ProtoControllers\Responsable::getInfosResponsables(\includes\SQL::singleton(),true);
    foreach ($infosResps as $infos) {
        $responsables[$infos['u_login']] = [
            'nom' => $infos['u_nom'],
            'prenom' => $infos['u_prenom'],
            'login' => $infos['u_login'],
            'isDansGroupe' => false
        ];

        if (NIL_INT !== $idGroupe) {
            $responsables[$infos['u_login']]['isDansGroupe'] = \App\ProtoControllers\Groupe::isGrandResponsableGroupe($infos['u_login'], [$idGroupe], \includes\SQL::singleton());
        }
    }
    return $responsables;
}

$config = new \App\Libraries\Configuration(\includes\SQL::singleton());

$return = '';
$PHP_SELF = filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_URL);
$message = '';
$infosGroupe = [
    'nom' => '',
    'doubleValidation' => '',
    'comment' => ''
];
$data = NULL;

$errorsLst = [];
if (!empty($_POST)) {
    if (0 >= (int) $this->postHtmlCommon($_POST, $errorsLst)) {
        $errors = '';
        if (!empty($errorsLst)) {
            foreach ($errorsLst as $key => $value) {
                if (is_array($value)) {
                    $value = implode(' / ', $value);
                }
                $errors .= '<li>' . $key . ' : ' . $value . '</li>';
            }
            $message = '<br><div class="alert alert-danger">' . _('erreur_recommencer') . '<ul>' . $errors . '</ul></div>';
        }
        $data = $this->FormData2Array($_POST);
    } else {
        if (key_exists('_METHOD', $_POST)) {
            redirect(ROOT_PATH . 'hr/hr_index.php?onglet=liste_groupe&notice=update', false);
        } else {
            redirect(ROOT_PATH . 'hr/hr_index.php?onglet=liste_groupe&notice=insert', false);
        }
    }
}

if (isset($data)) {
    $infosGroupe = [
        'nom' => $data['nom'],
        'comment' => $data['commentaire']
    ];
    if ($config->isDoubleValidationActive()) {
        $infosGroupe['doubleValidation'] = $data['isDoubleValidation'];
    }
} elseif (NIL_INT !== $idGroupe) {
    $infosGroupe = \App\ProtoControllers\Groupe::getInfosGroupe($idGroupe, \includes\SQL::singleton());
}

$selectId = uniqid();
$DivGrandRespId = uniqid();
if (NIL_INT !== $idGroupe) {
    $titre = '<h1>' . _('admin_modif_groupe_titre') . '</h1>';
} else {
    $titre = '<h1>' . _('admin_groupes_new_groupe') . '</h1>';
}

require_once VIEW_PATH . 'Groupe/Edition.php';
