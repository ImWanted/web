<?php
/*************************************************************************************************
Libertempo : Gestion Interactive des Congés
Copyright (C) 2005 (cedric chauvineau)

Ce programme est libre, vous pouvez le redistribuer et/ou le modifier selon les 
termes de la Licence Publique Générale GNU publiée par la Free Software Foundation.
Ce programme est distribué car potentiellement utile, mais SANS AUCUNE GARANTIE, 
ni explicite ni implicite, y compris les garanties de commercialisation ou d'adaptation 
dans un but spécifique. Reportez-vous à la Licence Publique Générale GNU pour plus de détails.
Vous devez avoir reçu une copie de la Licence Publique Générale GNU en même temps 
que ce programme ; si ce n'est pas le cas, écrivez à la Free Software Foundation, 
Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, États-Unis.
*************************************************************************************************
This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation; either 
version 2 of the License, or any later version.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*************************************************************************************************/
namespace responsable;

/**
* Regroupement des fonctions liées au responsable
*/
class Fonctions
{
    // on insert l'ajout de conges dans la table periode
    public static function insert_ajout_dans_periode($DEBUG, $login, $nb_jours, $id_type_abs, $commentaire)
    {
        $date_today=date("Y-m-d");

        $result=insert_dans_periode($login, $date_today, "am", $date_today, "am", $nb_jours, $commentaire, $id_type_abs, "ajout", 0,  $DEBUG);
    }

    public static function ajout_global_groupe($choix_groupe, $tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all,  $DEBUG=FALSE)
    {
        // $tab_new_nb_conges_all[$id_conges]= nb_jours
        // $tab_calcul_proportionnel[$id_conges]= TRUE / FALSE

        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        // recup de la liste des users d'un groupe donné 
        $list_users = get_list_users_du_groupe($choix_groupe,  $DEBUG);

        foreach($tab_new_nb_conges_all as $id_conges => $nb_jours)
        {
            if($nb_jours!=0)
            {
                $comment = $tab_new_comment_all[$id_conges];

                $sql1="SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ($list_users) ORDER BY u_login ";
                $ReqLog1 = \includes\SQL::query($sql1);

                while ($resultat1 = $ReqLog1->fetch_array()) 
                {
                    $current_login  =$resultat1["u_login"];
                    $current_quotite=$resultat1["u_quotite"];

                    if( (!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=TRUE) )
                        $nb_conges=$nb_jours;
                    else
                        // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2 
                        $nb_conges = (ROUND(($nb_jours*($current_quotite/100))*2))/2  ;
                    $nb_conges_ok = verif_saisie_decimal($nb_conges, $DEBUG);
                    if($nb_conges_ok){
                        // 1 : on update conges_solde_user
                        $req_update = "UPDATE conges_solde_user SET su_solde = su_solde+$nb_conges
                            WHERE  su_login = '$current_login' AND su_abs_id = $id_conges   ";
                        $ReqLog_update = \includes\SQL::query($req_update);

                        // 2 : on insert l'ajout de conges dans la table periode
                        // recup du nom du groupe
                        $groupename= get_group_name_from_id($choix_groupe,  $DEBUG);
                        $commentaire =  _('resp_ajout_conges_comment_periode_groupe') ." $groupename";

                        // ajout conges
                        \responsable\Fonctions::insert_ajout_dans_periode($DEBUG, $current_login, $nb_conges, $id_conges, $commentaire);
                    }

                }

                $group_name = get_group_name_from_id($choix_groupe,  $DEBUG);
                // 3 : Enregistrement du commentaire relatif à l'ajout de jours de congés 
                if( (!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=TRUE) )
                    $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
                else
                    $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
                log_action(0, "ajout", "groupe", $comment_log,  $DEBUG);
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            redirect( ROOT_PATH .'responsable/resp_index.php?session=' . $session );
        }
    }

    public static function ajout_global($tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        // $tab_new_nb_conges_all[$id_conges]= nb_jours
        // $tab_calcul_proportionnel[$id_conges]= TRUE / FALSE

        // recup de la liste de TOUS les users dont $resp_login est responsable 
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $list_users_du_resp = get_list_all_users_du_resp($_SESSION['userlogin'],  $DEBUG);
        if( $DEBUG ) { echo "list_all_users_du_resp = $list_users_du_resp<br>\n";}

        if( $DEBUG ) { echo "tab_new_nb_conges_all = <br>"; print_r($tab_new_nb_conges_all); echo "<br>\n" ;}
        if( $DEBUG ) { echo "tab_calcul_proportionnel = <br>"; print_r($tab_calcul_proportionnel); echo "<br>\n" ;}

        foreach($tab_new_nb_conges_all as $id_conges => $nb_jours)
        {
            if($nb_jours!=0)
            {
                $comment = $tab_new_comment_all[$id_conges];

                $sql1="SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ($list_users_du_resp) ORDER BY u_login ";
                $ReqLog1 = \includes\SQL::query($sql1);

                while($resultat1 = $ReqLog1->fetch_array()) 
                {
                    $current_login  =$resultat1["u_login"];
                    $current_quotite=$resultat1["u_quotite"];

                    if( (!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=TRUE) )
                        $nb_conges=$nb_jours;
                    else
                        // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2 
                        $nb_conges = (ROUND(($nb_jours*($current_quotite/100))*2))/2  ;

                    $nb_conges_ok = verif_saisie_decimal($nb_conges, $DEBUG);
                    if ($nb_conges_ok)
                    {
                        // 1 : update de la table conges_solde_user
                        $req_update = "UPDATE conges_solde_user SET su_solde = su_solde+$nb_conges
                            WHERE  su_login = '$current_login' AND su_abs_id = $id_conges   ";
                        $ReqLog_update = \includes\SQL::query($req_update);

                        // 2 : on insert l'ajout de conges GLOBAL (pour tous les users) dans la table periode
                        $commentaire =  _('resp_ajout_conges_comment_periode_all') ;
                        // ajout conges
                        \responsable\Fonctions::insert_ajout_dans_periode($DEBUG, $current_login, $nb_conges, $id_conges, $commentaire);
                    }		
                }
                // 3 : Enregistrement du commentaire relatif à l'ajout de jours de congés 
                if( (!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=TRUE) )
                    $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
                else
                    $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
                log_action(0, "ajout", "tous", $comment_log,  $DEBUG);
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            redirect( ROOT_PATH .'responsable/resp_index.php?session=' . $session );
        }
    }

    public static function ajout_conges($tab_champ_saisie, $tab_commentaire_saisie,  $DEBUG=FALSE) 
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id(); 

        foreach($tab_champ_saisie as $user_name => $tab_conges)   // tab_champ_saisie[$current_login][$id_conges]=valeur du nb de jours ajouté saisi
        {
            foreach($tab_conges as $id_conges => $user_nb_jours_ajout)
            {
                $user_nb_jours_ajout_float =(float) $user_nb_jours_ajout ;
                $valid=verif_saisie_decimal($user_nb_jours_ajout_float, $DEBUG);   //verif la bonne saisie du nombre décimal
                if($valid)
                {
                    if( $DEBUG ) {echo "$user_name --- $id_conges --- $user_nb_jours_ajout_float<br>\n";}

                    if($user_nb_jours_ajout_float!=0)
                    {
                        /* Modification de la table conges_users */
                        $sql1 = "UPDATE conges_solde_user SET su_solde = su_solde+$user_nb_jours_ajout_float WHERE su_login='$user_name' AND su_abs_id = $id_conges " ;
                        /* On valide l'UPDATE dans la table ! */
                        $ReqLog1 = \includes\SQL::query($sql1) ;

                        /*			// Enregistrement du commentaire relatif à l'ajout de jours de congés 
                                    $comment = $tab_commentaire_saisie[$user_name];
                                    $sql1 = "INSERT INTO conges_historique_ajout (ha_login, ha_date, ha_abs_id, ha_nb_jours, ha_commentaire)
                                    VALUES ('$user_name', NOW(), $id_conges, $user_nb_jours_ajout_float , '$comment')";
                                    $ReqLog1 = SQL::query($sql1) ;
                         */	
                        // on insert l'ajout de conges dans la table periode
                        $commentaire =  _('resp_ajout_conges_comment_periode_user') ;
                        \responsable\Fonctions::insert_ajout_dans_periode($DEBUG, $user_name, $user_nb_jours_ajout_float, $id_conges, $commentaire);
                    }
                }
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session\">";
        }
    }
    
    public static function affichage_saisie_globale_groupe($tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        /***********************************************************************/
        /* SAISIE GROUPE pour tous les utilisateurs d'un groupe du responsable */

        // on établi la liste complète des groupes dont on est le resp (ou le grd resp)
        $list_group_resp=get_list_groupes_du_resp($_SESSION['userlogin']);
        if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            $list_group_grd_resp=get_list_groupes_du_grand_resp($_SESSION['userlogin'],  $DEBUG);
        else
            $list_group_grd_resp="";

        $list_group="";
        if($list_group_resp!="")
        {
            $list_group = $list_group_resp;
            if($list_group_grd_resp!="")
                $list_group = $list_group.",".$list_group_grd_resp;
        }
        else
        {
            if($list_group_grd_resp!="")
                $list_group = $list_group_grd_resp;
        }


        if($list_group!="") //si la liste n'est pas vide ( serait le cas si n'est responsable d'aucun groupe)
        {
            echo "<h2>". _('resp_ajout_conges_ajout_groupe') ."</h2>\n";
            echo "<form action=\"$PHP_SELF?session=$session&onglet=ajout_conges\" method=\"POST\"> \n";
            echo "	<fieldset class=\"cal_saisie\">\n";
            echo "<div class=\"table-responsive\"><table class=\"table table-hover table-condensed table-striped\">\n";
            echo "	<tr>\n";
            echo "		<td class=\"big\">". _('resp_ajout_conges_choix_groupe') ." : </td>\n";
            // création du select pour le choix du groupe
            $text_choix_group="<select name=\"choix_groupe\" >";
            $sql_group = "SELECT g_gid, g_groupename FROM conges_groupe WHERE g_gid IN ($list_group) ORDER BY g_groupename "  ;
            $ReqLog_group = \includes\SQL::query($sql_group) ;

            while ($resultat_group = $ReqLog_group->fetch_array()) 
            {
                $current_group_id=$resultat_group["g_gid"];
                $current_group_name=$resultat_group["g_groupename"];
                $text_choix_group=$text_choix_group."<option value=\"$current_group_id\" >$current_group_name</option>";
            }
            $text_choix_group=$text_choix_group."</select>" ;

            echo "		<td colspan=\"3\">$text_choix_group</td>\n";
            echo "	</tr>\n";
            echo "<tr>\n";
            echo "<th colspan=\"2\">" . _('resp_ajout_conges_nb_jours_all_1') . ' ' . _('resp_ajout_conges_nb_jours_all_2') . "</th>\n";
            echo "<th>" ._('resp_ajout_conges_calcul_prop') . "</th>\n";
            echo "<th>" . _('divers_comment_maj_1') . "</th>\n";
            echo "</tr>\n";
            foreach($tab_type_conges as $id_conges => $libelle)
            {
                echo "	<tr>\n";
                echo "		<td><strong>$libelle<strong></td>\n";
                echo "		<td><input class=\"form-control\" type=\"text\" name=\"tab_new_nb_conges_all[$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\"></td>\n";
                echo "		<td>". _('resp_ajout_conges_oui') ." <input type=\"checkbox\" name=\"tab_calcul_proportionnel[$id_conges]\" value=\"TRUE\" checked></td>\n";
                echo "		<td><input class=\"form-control\" type=\"text\" name=\"tab_new_comment_all[$id_conges]\" size=\"30\" maxlength=\"200\" value=\"\"></td>\n";
                echo "	</tr>\n";
            }
            echo "	</table></div>\n";
            echo "<p>" . _('resp_ajout_conges_calcul_prop_arondi') . "! </p>\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_valid_groupe') ."\">\n";
            echo "	</fieldset>\n";
            echo "<input type=\"hidden\" name=\"ajout_groupe\" value=\"TRUE\">\n";
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "</form> \n";
        }
    }

    public static function affichage_saisie_globale_pour_tous($tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        /************************************************************/
        /* SAISIE GLOBALE pour tous les utilisateurs du responsable */
        echo "<h2>". _('resp_ajout_conges_ajout_all') ."</h2>\n";
        echo "<form action=\"$PHP_SELF?session=$session&onglet=ajout_conges\" method=\"POST\"> \n";
        echo "	<fieldset class=\"cal_saisie\">\n";
        echo "<div class=\"table-responsive\"><table class=\"table table-hover table-condensed table-striped\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th colspan=\"2\">" . _('resp_ajout_conges_nb_jours_all_1') . ' ' . _('resp_ajout_conges_nb_jours_all_2') . "</th>\n";
        echo "<th>" ._('resp_ajout_conges_calcul_prop') . "</th>\n";
        echo "<th>" . _('divers_comment_maj_1') . "</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        foreach($tab_type_conges as $id_conges => $libelle)
        {
            echo "	<tr>\n";
            echo "		<td><strong>$libelle<strong></td>\n";
            echo "		<td><input class=\"form-control\" type=\"text\" name=\"tab_new_nb_conges_all[$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\"></td>\n";
            echo "		<td>". _('resp_ajout_conges_oui') ." <input type=\"checkbox\" name=\"tab_calcul_proportionnel[$id_conges]\" value=\"TRUE\" checked></td>\n";
            echo "		<td><input class=\"form-control\" type=\"text\" name=\"tab_new_comment_all[$id_conges]\" size=\"30\" maxlength=\"200\" value=\"\"></td>\n";
            echo "	</tr>\n";
        }
        echo "</table></div>\n";
        // texte sur l'arrondi du calcul proportionnel
        echo "<p>" . _('resp_ajout_conges_calcul_prop_arondi') . "!</p>\n";
        // bouton valider
        echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_valid_global') ."\">\n";
        echo "</fieldset>\n";
        echo "<input type=\"hidden\" name=\"ajout_global\" value=\"TRUE\">\n";
        echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
        echo "</form> \n";
    }

    public static function affichage_saisie_user_par_user($tab_type_conges, $tab_type_conges_exceptionnels, $tab_all_users_du_resp, $tab_all_users_du_grand_resp,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        /************************************************************/
        /* SAISIE USER PAR USER pour tous les utilisateurs du responsable */
        echo "<h2>Ajout par utilisateur</h2>\n";
        echo " <form action=\"$PHP_SELF?session=$session&onglet=ajout_conges\" method=\"POST\"> \n";

        // Récupération des informations
        // Récup dans un tableau de tableau des informations de tous les users dont $_SESSION['userlogin'] est responsable
        //$tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin']);
        //$tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);

        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            // AFFICHAGE TITRES TABLEAU
            echo "<div class=\"table-responsive\"><table class=\"table table-hover table-condensed table-striped\">\n";
            echo "<thead>\n";
            echo "<tr align=\"center\">\n";
            echo "<th>". _('divers_nom_maj_1') ."</th>\n";
            echo "<th>". _('divers_prenom_maj_1') ."</th>\n";
            echo "<th>". _('divers_quotite_maj_1') ."</td>\n";
            foreach($tab_type_conges as $id_conges => $libelle)
            {
                echo "<th>$libelle<br><i>(". _('divers_solde') .")</i></th>\n";
                echo "<th>$libelle<br>". _('resp_ajout_conges_nb_jours_ajout') ."</th>\n" ;
            }
            if ($_SESSION['config']['gestion_conges_exceptionnels'])
            {
                foreach($tab_type_conges_exceptionnels as $id_conges => $libelle)
                {
                    echo "<th>$libelle<br><i>(". _('divers_solde') .")</i></th>\n";
                    echo "<th>$libelle<br>". _('resp_ajout_conges_nb_jours_ajout') ."</th>\n" ;
                }
            }
            echo "<th>". _('divers_comment_maj_1') ."<br></th>\n" ;
            echo"</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // AFFICHAGE LIGNES TABLEAU
            $cpt_lignes=0 ;
            $tab_champ_saisie_conges=array();

            $i = true;
            // affichage des users dont on est responsable :
            foreach($tab_all_users_du_resp as $current_login => $tab_current_user)
            {		
                echo '<tr class="'.($i?'i':'p').'">';
                //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
                $tab_conges=$tab_current_user['conges']; 

                /** sur la ligne ,   **/
                echo "<td>".$tab_current_user['nom']."</td>\n";
                echo "<td>".$tab_current_user['prenom']."</td>\n";
                echo "<td>".$tab_current_user['quotite']."%</td>\n";

                foreach($tab_type_conges as $id_conges => $libelle)
                {
                    /** le champ de saisie est <input type="text" name="tab_champ_saisie[valeur de u_login][id_du_type_de_conges]" value="[valeur du nb de jours ajouté saisi]"> */
                    $champ_saisie_conges="<input class=\"form-control\" type=\"text\" name=\"tab_champ_saisie[$current_login][$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\">";
                    echo "<td>".$tab_conges[$libelle]['nb_an']." <i>(".$tab_conges[$libelle]['solde'].")</i></td>\n";
                    echo "<td align=\"center\" class=\"histo\">$champ_saisie_conges</td>\n" ;
                }
                if ($_SESSION['config']['gestion_conges_exceptionnels'])
                {
                    foreach($tab_type_conges_exceptionnels as $id_conges => $libelle)
                    {
                        /** le champ de saisie est <input type="text" name="tab_champ_saisie[valeur de u_login][id_du_type_de_conges]" value="[valeur du nb de jours ajouté saisi]"> */
                        $champ_saisie_conges="<input class=\"form-control\" type=\"text\" name=\"tab_champ_saisie[$current_login][$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\">";
                        echo "<td><i>(".$tab_conges[$libelle]['solde'].")</i></td>\n";
                        echo "<td align=\"center\" class=\"histo\">$champ_saisie_conges</td>\n" ;
                    }
                }
                echo "<td align=\"center\" class=\"histo\"><input class=\"form-control\" type=\"text\" name=\"tab_commentaire_saisie[$current_login]\" size=\"30\" maxlength=\"200\" value=\"\"></td>\n";
                echo "</tr>\n";
                $cpt_lignes++ ;
                $i = !$i;
            }

            // affichage des users dont on est grand responsable :
            if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            {
                $nb_colspan=50;
                echo "<tr align=\"center\"><td class=\"histo\" style=\"background-color: #CCC;\" colspan=\"$nb_colspan\"><i>". _('resp_etat_users_titre_double_valid') ."</i></td></tr>\n";

                $i = true;
                foreach($tab_all_users_du_grand_resp as $current_login => $tab_current_user)
                {		
                    echo '<tr class="'.($i?'i':'p').'">';
                    //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
                    $tab_conges=$tab_current_user['conges']; 

                    /** sur la ligne ,   **/
                    echo "<td>".$tab_current_user['nom']."</td>\n";
                    echo "<td>".$tab_current_user['prenom']."</td>\n";
                    echo "<td>".$tab_current_user['quotite']."%</td>\n";

                    foreach($tab_type_conges as $id_conges => $libelle)
                    {
                        /** le champ de saisie est <input type="text" name="tab_champ_saisie[valeur de u_login][id_du_type_de_conges]" value="[valeur du nb de jours ajouté saisi]"> */
                        $champ_saisie_conges="<input type=\"text\" name=\"tab_champ_saisie[$current_login][$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\">";
                        echo "<td>".$tab_conges[$libelle]['nb_an']." <i>(".$tab_conges[$libelle]['solde'].")</i></td>\n";
                        echo "<td align=\"center\" class=\"histo\">$champ_saisie_conges</td>\n" ;
                    }
                    if ($_SESSION['config']['gestion_conges_exceptionnels'])
                    {
                        foreach($tab_type_conges_exceptionnels as $id_conges => $libelle)
                        {
                            /** le champ de saisie est <input type="text" name="tab_champ_saisie[valeur de u_login][id_du_type_de_conges]" value="[valeur du nb de jours ajouté saisi]"> */
                            $champ_saisie_conges="<input type=\"text\" name=\"tab_champ_saisie[$current_login][$id_conges]\" size=\"6\" maxlength=\"6\" value=\"0\">";
                            echo "<td><i>(".$tab_conges[$libelle]['solde'].")</i></td>\n";
                            echo "<td align=\"center\" class=\"histo\">$champ_saisie_conges</td>\n" ;
                        }
                    }
                    echo "<td align=\"center\" class=\"histo\"><input type=\"text\" name=\"tab_commentaire_saisie[$current_login]\" size=\"30\" maxlength=\"200\" value=\"\"></td>\n";
                    echo "</tr>\n";
                    $cpt_lignes++ ;
                    $i = !$i;
                }
            }

            echo "</tbody>\n";
            echo "</table></div>\n\n";

            echo "<input type=\"hidden\" name=\"ajout_conges\" value=\"TRUE\">\n";
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_submit') ."\">\n";
            echo " </form> \n";
        }
    }

    public static function saisie_ajout( $tab_type_conges,  $DEBUG)
    {

        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        // recup du tableau des types de conges (seulement les congesexceptionnels )
        if ($_SESSION['config']['gestion_conges_exceptionnels']) 
        {
            $tab_type_conges_exceptionnels = recup_tableau_types_conges_exceptionnels();
            if( $DEBUG ) { echo "tab_type_conges_exceptionnels = "; print_r($tab_type_conges_exceptionnels); echo "<br><br>\n";}
        }
        else
            $tab_type_conges_exceptionnels = array();

        // recup de la liste de TOUS les users dont $resp_login est responsable 
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin']);
        $tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);
        if( $DEBUG ) { echo "tab_all_users_du_resp =<br>\n"; print_r($tab_all_users_du_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_all_users_du_grand_resp =<br>\n"; print_r($tab_all_users_du_grand_resp); echo "<br>\n"; }

        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            /************************************************************/
            /* SAISIE GLOBALE pour tous les utilisateurs du responsable */
            \responsable\Fonctions::affichage_saisie_globale_pour_tous($tab_type_conges,  $DEBUG);
            echo "<br>\n";

            /***********************************************************************/
            /* SAISIE GROUPE pour tous les utilisateurs d'un groupe du responsable */
            if( $_SESSION['config']['gestion_groupes'] )
            {
                \responsable\Fonctions::affichage_saisie_globale_groupe($tab_type_conges,  $DEBUG);
            }

            echo "<hr/>\n";

            /************************************************************/
            /* SAISIE USER PAR USER pour tous les utilisateurs du responsable */
            \responsable\Fonctions::affichage_saisie_user_par_user($tab_type_conges, $tab_type_conges_exceptionnels, $tab_all_users_du_resp, $tab_all_users_du_grand_resp,  $DEBUG);
            echo "<br>\n";

        }
        else
            echo  _('resp_etat_aucun_user') ."<br>\n";

    }
    
    /**
     * Encapsule le comportement du module d'ajout de congés
     *
     * @return void
     * @access public
     * @static
     */
    public static function ajoutCongesModule($tab_type_cong, $DEBUG = false)
    {
        // echo $twig->render('test.html');
        //var pour resp_ajout_conges_all.php
        $ajout_conges            = getpost_variable('ajout_conges');
        $tab_champ_saisie        = getpost_variable('tab_champ_saisie');
        $tab_commentaire_saisie        = getpost_variable('tab_commentaire_saisie');
        //$tab_champ_saisie_rtt    = getpost_variable('tab_champ_saisie_rtt') ;
        $ajout_global            = getpost_variable('ajout_global');
        $ajout_groupe            = getpost_variable('ajout_groupe');
        $choix_groupe            = getpost_variable('choix_groupe');
        $tab_new_nb_conges_all   = getpost_variable('tab_new_nb_conges_all');
        $tab_calcul_proportionnel = getpost_variable('tab_calcul_proportionnel');
        $tab_new_comment_all     = getpost_variable('tab_new_comment_all');


        if( $DEBUG ) { echo "tab_new_nb_conges_all = <br>"; print_r($tab_new_nb_conges_all); echo "<br>\n" ;}
        if( $DEBUG ) { echo "tab_calcul_proportionnel = <br>"; print_r($tab_calcul_proportionnel); echo "<br>\n" ;}


        // titre
        echo "<h1>". _('resp_ajout_conges_titre') ."</h1>\n";

        if($ajout_conges=="TRUE")
        {
            \responsable\Fonctions::ajout_conges($tab_champ_saisie, $tab_commentaire_saisie, $DEBUG);
        }
        elseif($ajout_global=="TRUE")
        {
            \responsable\Fonctions::ajout_global($tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all,  $DEBUG);
        }
        elseif($ajout_groupe=="TRUE")
        {
            \responsable\Fonctions::ajout_global_groupe($choix_groupe, $tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all,  $DEBUG);
        }
        else
        {
            \responsable\Fonctions::saisie_ajout($tab_type_cong, $DEBUG);
        }
    }

    // calcule de la date limite d'utilisation des reliquats (si on utilise une date limite et qu'elle n'est pas encore calculée) et stockage dans la table
    public static function set_nouvelle_date_limite_reliquat( $DEBUG=FALSE)
    {
        //si on autorise les reliquats
        if($_SESSION['config']['autorise_reliquats_exercice'])
        {
            // s'il y a une date limite d'utilisationdes reliquats (au format jj-mm)
            if($_SESSION['config']['jour_mois_limite_reliquats']!=0)
            {
                // nouvelle date limite au format aaa-mm-jj
                $t=explode("-", $_SESSION['config']['jour_mois_limite_reliquats']);
                $new_date_limite = date("Y")."-".$t[1]."-".$t[0];

                //si la date limite n'a pas encore été updatée
                if($_SESSION['config']['date_limite_reliquats'] < $new_date_limite)
                {
                    /* Modification de la table conges_appli */
                    $sql_update= "UPDATE conges_appli SET appli_valeur = '$new_date_limite' WHERE appli_variable='date_limite_reliquats' " ;
                    $ReqLog_update = \includes\SQL::query($sql_update) ;

                }
            }
        }
    }

    // cloture / debut d'exercice pour TOUS les users d'un groupe'
    public static function cloture_globale_groupe($group_id, $tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id(); 

        // recup de la liste de TOUS les users du groupe
        $tab_all_users_du_groupe=recup_infos_all_users_du_groupe($group_id,  $DEBUG);
        if( $DEBUG ) { echo "tab_all_users_du_groupe =<br>\n"; print_r($tab_all_users_du_groupe); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_type_conges =<br>\n"; print_r($tab_type_conges); echo "<br>\n"; }

        $comment_cloture =  _('resp_cloture_exercice_commentaire') ." ".date("m/Y");

        if(count($tab_all_users_du_groupe)!=0)
        {
            // traitement des users dont on est responsable :
            foreach($tab_all_users_du_groupe as $current_login => $tab_current_user)
            {		
                cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $comment_cloture,  $DEBUG);
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session\">";
        }

    }

    // cloture / debut d'exercice pour TOUS les users du resp (ou grand resp)
    public static function cloture_globale($tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id(); 

        // recup de la liste de TOUS les users dont $resp_login est responsable 
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin']);
        $tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);
        if( $DEBUG ) { echo "tab_all_users_du_resp =<br>\n"; print_r($tab_all_users_du_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_all_users_du_grand_resp =<br>\n"; print_r($tab_all_users_du_grand_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_type_conges =<br>\n"; print_r($tab_type_conges); echo "<br>\n"; }

        $comment_cloture =  _('resp_cloture_exercice_commentaire') ." ".date("m/Y");

        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            // traitement des users dont on est responsable :
            foreach($tab_all_users_du_resp as $current_login => $tab_current_user)
            {		
                cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $comment_cloture,  $DEBUG);
            }
            // traitement des users dont on est grand responsable :
            if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            {
                foreach($tab_all_users_du_grand_resp as $current_login => $tab_current_user)
                {		
                    cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $comment_cloture,  $DEBUG);
                }
            }	
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session\">";
        }
    }

    // verifie si tous les users on été basculés de l'exerccice précédent vers le suivant.
    // si oui : on incrémente le num_exercice de l'application
    public static function update_appli_num_exercice( $DEBUG=FALSE)
    {
        // verif
        $appli_num_exercice = $_SESSION['config']['num_exercice'] ;
        $sql_verif = 'SELECT u_login FROM conges_users WHERE u_login != \'admin\' AND u_login != \'conges\' AND u_num_exercice != '. \includes\SQL::quote($appli_num_exercice).';';
        $ReqLog_verif = \includes\SQL::query($sql_verif);

        if($ReqLog_verif->num_rows == 0) {
            /* Modification de la table conges_appli */
            $sql_update= 'UPDATE conges_appli SET appli_valeur = appli_valeur+1 WHERE appli_variable=\'num_exercice\' ;';
            $ReqLog_update = \includes\SQL::query($sql_update) ;

            // ecriture dans les logs
            $new_appli_num_exercice = $appli_num_exercice+1 ;
            log_action(0, '', '', 'fin/debut exercice (appli_num_exercice : '.$appli_num_exercice.' -> '.$new_appli_num_exercice.')',  $DEBUG);
        } 
    }

    public static function cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $commentaire,  $DEBUG=FALSE)
    {
    // si le num d'exercice du user est < à celui de l'appli (il n'a pas encore été basculé): on le bascule d'exercice
    if($tab_current_user['num_exercice'] < $_SESSION['config']['num_exercice'])
    {
    // calcule de la date limite d'utilisation des reliquats (si on utilise une date limite et qu'elle n'est pas encore calculée)
    \responsable\Fonctions::set_nouvelle_date_limite_reliquat( $DEBUG);

    //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
    $tab_conges_current_user=$tab_current_user['conges']; 
    foreach($tab_type_conges as $id_conges => $libelle)
    {
    $user_nb_jours_ajout_an = $tab_conges_current_user[$libelle]['nb_an'];
    $user_solde_actuel=$tab_conges_current_user[$libelle]['solde'];
    $user_reliquat_actuel=$tab_conges_current_user[$libelle]['reliquat'];

    if( $DEBUG ) {echo "$current_login --- $id_conges --- $user_nb_jours_ajout_an<br>\n";}

    /**********************************************/
    /* Modification de la table conges_solde_user */

    if($_SESSION['config']['autorise_reliquats_exercice'])
    {
        // ATTENTION : si le solde du user est négatif, on ne compte pas de reliquat et le nouveau solde est nb_jours_an + le solde actuel (qui est négatif)
        if($user_solde_actuel>0)
        {
            //calcul du reliquat pour l'exercice suivant
            if($_SESSION['config']['nb_maxi_jours_reliquats']!=0)
            {
                if($user_solde_actuel <= $_SESSION['config']['nb_maxi_jours_reliquats'])
                    $new_reliquat = $user_solde_actuel ;
                else
                    $new_reliquat = $_SESSION['config']['nb_maxi_jours_reliquats'] ;
            }
            else
                $new_reliquat = $user_reliquat_actuel + $user_solde_actuel ;

            //
            // update D'ABORD du reliquat
            $sql_reliquat = "UPDATE conges_solde_user SET su_reliquat = $new_reliquat WHERE su_login='$current_login' AND su_abs_id = $id_conges " ;
            $ReqLog_reliquat = \includes\SQL::query($sql_reliquat) ;
        }
        else
            $new_reliquat = $user_solde_actuel ; // qui est nul ou negatif


        $new_solde = $user_nb_jours_ajout_an + $new_reliquat  ;

        // update du solde
        $sql_solde = 'UPDATE conges_solde_user SET su_solde = \''.$new_solde.'\' WHERE su_login="'. \includes\SQL::quote($current_login).'" AND su_abs_id ="'. \includes\SQL::quote($id_conges).'" ';
        $ReqLog_solde = \includes\SQL::query($sql_solde) ;
    }
    else
    {
        // ATTENTION : meme si on accepte pas les reliquats, si le solde du user est négatif, il faut le reporter: le nouveau solde est nb_jours_an + le solde actuel (qui est négatif)
        if($user_solde_actuel < 0)
            $new_solde = $user_nb_jours_ajout_an + $user_solde_actuel ; // qui est nul ou negatif
        else
            $new_solde = $user_nb_jours_ajout_an ;

        $sql_solde = 'UPDATE conges_solde_user SET su_solde = \''.$new_solde.'\' WHERE su_login="'. \includes\SQL::quote($current_login).'"  AND su_abs_id = "'. \includes\SQL::quote($id_conges).'" ';
        $ReqLog_solde = \includes\SQL::query($sql_solde) ;
    }

    /* Modification de la table conges_users */
    // ATTENTION : ne pas faire "SET u_num_exercice = u_num_exercice+1" dans la requete SQL car on incrémenterait pour chaque type d'absence !
    $new_num_exercice=$_SESSION['config']['num_exercice'] ;
    $sql2 = 'UPDATE conges_users SET u_num_exercice = \''.$new_num_exercice.'\' WHERE u_login="'. \includes\SQL::quote($current_login).'" ';
    $ReqLog2 = \includes\SQL::query($sql2) ;

    // on insert l'ajout de conges dans la table periode (avec le commentaire)
    $date_today=date("Y-m-d");
    insert_dans_periode($current_login, $date_today, "am", $date_today, "am", $user_nb_jours_ajout_an, $commentaire, $id_conges, "ajout", 0,  $DEBUG);
    }

    // on incrémente le num_exercice de l'application si tous les users on été basculés.
    \responsable\Fonctions::update_appli_num_exercice( $DEBUG);
    }	
    }

    // cloture / debut d'exercice user par user pour les users du resp (ou grand resp)
    public static function cloture_users($tab_type_conges, $tab_cloture_users, $tab_commentaire_saisie,  $DEBUG=FALSE) 
    {
    //$DEBUG=TRUE;
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id(); 

        // recup de la liste de TOUS les users dont $resp_login est responsable 
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin']);
        $tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);
        if( $DEBUG ) { echo "tab_all_users_du_resp =<br>\n"; print_r($tab_all_users_du_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_all_users_du_grand_resp =<br>\n"; print_r($tab_all_users_du_grand_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_type_conges =<br>\n"; print_r($tab_type_conges); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_cloture_users =<br>\n"; print_r($tab_cloture_users); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_commentaire_saisie =<br>\n"; print_r($tab_commentaire_saisie); echo "<br>\n"; }
        
        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            // traitement des users dont on est responsable :
            foreach($tab_all_users_du_resp as $current_login => $tab_current_user)
            {		
                // tab_cloture_users[$current_login]=TRUE si checkbox "cloturer" est cochée
                if( (isset($tab_cloture_users[$current_login])) && ($tab_cloture_users[$current_login]=TRUE) )
                {
                    $commentaire = $tab_commentaire_saisie[$current_login];
                    \responsable\Fonctions::cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $commentaire,  $DEBUG);
                }
            }
            // traitement des users dont on est grand responsable :
            if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            {
                foreach($tab_all_users_du_grand_resp as $current_login => $tab_current_user)
                {		
                    // tab_cloture_users[$current_login]=TRUE si checkbox "cloturer" est cochée
                    if( (isset($tab_cloture_users[$current_login])) && ($tab_cloture_users[$current_login]=TRUE) )
                    {
                        $commentaire = $tab_commentaire_saisie[$current_login];
                        \responsable\Fonctions::cloture_current_year_for_login($current_login, $tab_current_user, $tab_type_conges, $commentaire,  $DEBUG);
                    }
                }
            }	
        }
        
        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo " ". _('form_modif_ok') ." <br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session\">";
        }

    }

    public static function affichage_cloture_globale_groupe($tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;
        
        /***********************************************************************/
        /* SAISIE GROUPE pour tous les utilisateurs d'un groupe du responsable */

        // on établi la liste complète des groupes dont on est le resp (ou le grd resp)
        $list_group_resp=get_list_groupes_du_resp($_SESSION['userlogin']);
        if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            $list_group_grd_resp=get_list_groupes_du_grand_resp($_SESSION['userlogin'],  $DEBUG);
        else
            $list_group_grd_resp="";
            
        $list_group="";
        if($list_group_resp!="")
        {
            $list_group = $list_group_resp;
            if($list_group_grd_resp!="")
                $list_group = $list_group.",".$list_group_grd_resp;
        }
        else
        {
            if($list_group_grd_resp!="")
                $list_group = $list_group_grd_resp;
        }
        
            
        if($list_group!="") //si la liste n'est pas vide ( serait le cas si n'est responsable d'aucun groupe)
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\"> \n";
            echo "<table>\n";
            echo "<tr><td align=\"center\">\n";
            echo "	<fieldset class=\"cal_saisie\">\n";
            echo "	<legend class=\"boxlogin\">". _('resp_cloture_exercice_groupe') ."</legend>\n";
            
            echo "	<table>\n";
            echo "	<tr>\n";

                // création du select pour le choix du groupe
                $text_choix_group="<select name=\"choix_groupe\" >";
                $sql_group = "SELECT g_gid, g_groupename FROM conges_groupe WHERE g_gid IN ($list_group) ORDER BY g_groupename "  ;
                $ReqLog_group = \includes\SQL::query($sql_group) ;
                    
                while ($resultat_group = $ReqLog_group->fetch_array()) 
                {
                    $current_group_id=$resultat_group["g_gid"];
                    $current_group_name=$resultat_group["g_groupename"];
                    $text_choix_group=$text_choix_group."<option value=\"$current_group_id\" >$current_group_name</option>";
                }
                $text_choix_group=$text_choix_group."</select>" ;

            echo "		<td class=\"big\">". _('resp_ajout_conges_choix_groupe') ." : $text_choix_group</td>\n";
            
            echo "	</tr>\n";
            echo "	<tr>\n";
            echo "		<td class=\"big\">". _('resp_cloture_exercice_for_groupe_text_confirmer') ." </td>\n";
            echo "	</tr>\n";
            echo "	<tr>\n";
            echo "		<td align=\"center\"><input class=\"btn\" type=\"submit\" value=\"". _('form_valid_cloture_group') ."\"></td>\n";
            echo "	</tr>\n";
            echo "	</table>\n";
            
            echo "	</fieldset>\n";
            echo "</td></tr>\n";
            echo "</table>\n";

            echo "<input type=\"hidden\" name=\"onglet\" value=\"cloture_exercice\">\n";
            echo "<input type=\"hidden\" name=\"cloture_groupe\" value=\"TRUE\">\n";
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "</form> \n";
        }
    }

    public static function affichage_cloture_globale_pour_tous($tab_type_conges,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        /************************************************************/
        /* CLOTURE EXERCICE GLOBALE pour tous les utilisateurs du responsable */

        echo "<form action=\"$PHP_SELF?session=$session&onglet=cloture_exercice\" method=\"POST\"> \n";
        echo "<table>\n";
        echo "<tr><td align=\"center\">\n";
        echo "	<fieldset class=\"cal_saisie\">\n";
        echo "	<legend class=\"boxlogin\">". _('resp_cloture_exercice_all') ."</legend>\n";
        echo "	<table>\n";
        echo "	<tr>\n";
        echo "		<td class=\"big\">&nbsp;&nbsp;&nbsp;". _('resp_cloture_exercice_for_all_text_confirmer') ." &nbsp;&nbsp;&nbsp;</td>\n";
        echo "	</tr>\n";
        // bouton valider
        echo "	<tr>\n";
        echo "		<td colspan=\"5\" align=\"center\"><input class=\"btn\" type=\"submit\" value=\"". _('form_valid_cloture_global') ."\"></td>\n";
        echo "	</tr>\n";
        echo "	</table>\n";
        echo "	</fieldset>\n";
        echo "</td></tr>\n";
        echo "</table>\n";
        echo "<input type=\"hidden\" name=\"cloture_globale\" value=\"TRUE\">\n";
        echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
        echo "</form> \n";
    }

    public static function affiche_ligne_du_user($current_login, $tab_type_conges, $tab_current_user)
    {
        echo " <tr align=\"center\">\n";
        //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
        $tab_conges=$tab_current_user['conges']; 

        /** sur la ligne ,   **/
        echo " <td>".$tab_current_user['nom']."</td>\n";
        echo " <td>".$tab_current_user['prenom']."</td>\n";
        echo " <td>".$tab_current_user['quotite']."%</td>\n";

        foreach($tab_type_conges as $id_conges => $libelle)
        {
            echo " <td>".$tab_conges[$libelle]['nb_an']." <i>(".$tab_conges[$libelle]['solde'].")</i></td>\n";
        }

        // si le num d'exercice du user est < à celui de l'appli (il n'a pas encore été basculé): on peut le cocher
        if($tab_current_user['num_exercice'] < $_SESSION['config']['num_exercice'])
            echo "	<td align=\"center\" class=\"histo\"><input type=\"checkbox\" name=\"tab_cloture_users[$current_login]\" value=\"TRUE\" checked></td>\n";
        else
            echo "	<td align=\"center\" class=\"histo\"><img src=\"". TEMPLATE_PATH ."img/stop.png\" width=\"16\" height=\"16\" border=\"0\" ></td>\n";

        $comment_cloture =  _('resp_cloture_exercice_commentaire') ." ".date("m/Y");
        echo "	<td align=\"center\" class=\"histo\"><input type=\"text\" name=\"tab_commentaire_saisie[$current_login]\" size=\"20\" maxlength=\"200\" value=\"$comment_cloture\"></td>\n";
        echo " 	</tr>\n";
    }

    public static function affichage_cloture_user_par_user($tab_type_conges, $tab_all_users_du_resp, $tab_all_users_du_grand_resp,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        /************************************************************/
        /* CLOTURE EXERCICE USER PAR USER pour tous les utilisateurs du responsable */

        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            echo "<form action=\"$PHP_SELF?session=$session&onglet=cloture_exercice\" method=\"POST\"> \n";
            echo "<table>\n";
            echo "<tr>\n";
            echo "<td align=\"center\">\n";
            echo "<fieldset class=\"cal_saisie\">\n";
            echo "<legend class=\"boxlogin\">". _('resp_cloture_exercice_users') ."</legend>\n";
            echo "	<table>\n";
            echo "	<tr>\n";
            echo "	<td align=\"center\">\n";

            // AFFICHAGE TITRES TABLEAU
            echo "	<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"700\">\n";
            echo "	<thead>\n";
            echo "	<tr align=\"center\">\n";
            echo "	<th>". _('divers_nom_maj_1') ."</th>\n";
            echo "	<th>". _('divers_prenom_maj_1') ."</th>\n";
            echo "	<th>". _('divers_quotite_maj_1') ."</th>\n";
            foreach($tab_type_conges as $id_conges => $libelle)
            {
                echo "	<th>$libelle<br><i>(". _('divers_solde') .")</i></th>\n";
            }
            echo "	<th>". _('divers_cloturer_maj_1') ."<br></th>\n" ;
            echo "	<th>". _('divers_comment_maj_1') ."<br></th>\n" ;
            echo "	</tr>\n";
            echo "	</thead>\n";
            echo "	<tbody>\n";

            // AFFICHAGE LIGNES TABLEAU

            // affichage des users dont on est responsable :
            foreach($tab_all_users_du_resp as $current_login => $tab_current_user)
            {		
                \responsable\Fonctions::affiche_ligne_du_user($current_login, $tab_type_conges, $tab_current_user);
            }

            // affichage des users dont on est grand responsable :
            if( ($_SESSION['config']['double_validation_conges']) && ($_SESSION['config']['grand_resp_ajout_conges']) )
            {
                $nb_colspan=50;
                echo "<tr align=\"center\"><td class=\"histo\" style=\"background-color: #CCC;\" colspan=\"$nb_colspan\"><i>". _('resp_etat_users_titre_double_valid') ."</i></td></tr>\n";

                foreach($tab_all_users_du_grand_resp as $current_login => $tab_current_user)
                {		
                    \responsable\Fonctions::affiche_ligne_du_user($current_login, $tab_type_conges, $tab_current_user);
                }
            }	
            echo "	</tbody>\n";
            echo "	</table>\n\n";

            echo "	</td>\n";
            echo "	</tr>\n";
            echo "	<tr>\n";
            echo "	<td align=\"center\">\n";
            echo "	<input class=\"btn\" type=\"submit\" value=\"". _('form_submit') ."\">\n";
            echo "	</td>\n";
            echo "	</tr>\n";
            echo "	</table>\n";

            echo "</fieldset>\n";
            echo "</td></tr>\n";
            echo "</table>\n";
            echo "<input type=\"hidden\" name=\"cloture_users\" value=\"TRUE\">\n";
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "</form> \n";
        }
    }

    public static function saisie_cloture( $tab_type_conges,  $DEBUG)
    {

        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;

        // recup de la liste de TOUS les users dont $resp_login est responsable 
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin']);
        $tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);
        if( $DEBUG ) { echo "tab_all_users_du_resp =<br>\n"; print_r($tab_all_users_du_resp); echo "<br>\n"; }
        if( $DEBUG ) { echo "tab_all_users_du_grand_resp =<br>\n"; print_r($tab_all_users_du_grand_resp); echo "<br>\n"; }

        if( (count($tab_all_users_du_resp)!=0) || (count($tab_all_users_du_grand_resp)!=0) )
        {
            /************************************************************/
            /* SAISIE GLOBALE pour tous les utilisateurs du responsable */
            affichage_cloture_globale_pour_tous($tab_type_conges,  $DEBUG);
            echo "<br>\n";

            /***********************************************************************/
            /* SAISIE GROUPE pour tous les utilisateurs d'un groupe du responsable */
            if( $_SESSION['config']['gestion_groupes'] )
            {
                \responsable\Fonctions::affichage_cloture_globale_groupe($tab_type_conges,  $DEBUG);
            }
            echo "<br>\n";

            /************************************************************/
            /* SAISIE USER PAR USER pour tous les utilisateurs du responsable */
            \responsable\Fonctions::affichage_cloture_user_par_user($tab_type_conges, $tab_all_users_du_resp, $tab_all_users_du_grand_resp,  $DEBUG);
            echo "<br>\n";

        }
        else
            echo  _('resp_etat_aucun_user') ."<br>\n";
    }

    /**
     * Encapsule le comportement du module de cloture d'exercice
     *
     * @return void
     * @access public
     * @static
     */
    public static function clotureExerciceModule($DEBUG = false)
    {
        $choix_groupe            = getpost_variable('choix_groupe');
        $cloture_users           = getpost_variable('cloture_users');
        $cloture_globale         = getpost_variable('cloture_globale');
        $cloture_groupe          = getpost_variable('cloture_groupe');
        $tab_cloture_users       = getpost_variable('tab_cloture_users');
        $tab_commentaire_saisie       = getpost_variable('tab_commentaire_saisie');
        /*************************************/

        header_popup( $_SESSION['config']['titre_resp_index'] );


        /*************************************/
        /***  suite de la page             ***/
        /*************************************/

        /** initialisation des tableaux des types de conges/absences  **/
        // recup du tableau des types de conges (conges et congesexceptionnels)
        // on concatene les 2 tableaux
        $tab_type_cong = ( recup_tableau_types_conges( $DEBUG) + recup_tableau_types_conges_exceptionnels( $DEBUG)  );

        // titre
        echo "<H2>". _('resp_cloture_exercice_titre') ."</H2>\n\n";

        if($cloture_users=="TRUE")
        {
            \responsable\Fonctions::cloture_users($tab_type_cong, $tab_cloture_users, $tab_commentaire_saisie,  $DEBUG);
        }
        elseif($cloture_globale=="TRUE")
        {
            \responsable\Fonctions::cloture_globale($tab_type_cong,  $DEBUG);
        }
        elseif($cloture_groupe=="TRUE")
        {
            \responsable\Fonctions::cloture_globale_groupe($choix_groupe, $tab_type_cong,  $DEBUG);
        }
        else
        {
            \responsable\Fonctions::saisie_cloture($tab_type_cong, $DEBUG);
        }
        bottom();
    }
    
    /**
     * Encapsule le comportement du module de page principale
     *
     * @return void
     * @access public
     * @static
     */
    public static function pagePrincipaleModule($tab_type_cong, $session, $DEBUG = false)
    {
        /***********************************/
        // AFFICHAGE ETAT CONGES TOUS USERS

        /***********************************/
        // AFFICHAGE TABLEAU (premiere ligne)
        echo "<h1>". _('resp_traite_user_etat_conges') ."</h1>";

        echo "<table class=\"table table-hover table-responsive table-condensed table-striped\">\n";
        echo '<thead>';

        $nb_colonnes = 0;

        echo "<tr>\n";
        echo '<th>'. _('divers_nom_maj') .'</th>';
        echo '<th>'. _('divers_prenom_maj') .'</th>';
        echo '<th>'. _('divers_quotite_maj_1') .'</th>' ;
        $nb_colonnes = 3;
        foreach($tab_type_cong as $id_conges => $libelle)
        {
            // cas d'une absence ou d'un congé
            echo "<th> $libelle"." / ". _('divers_an_maj') .'</th>';
            echo '<th>'. _('divers_solde_maj') ." ".$libelle .'</th>';
            $nb_colonnes += 2;
        }
        // conges exceptionnels
        if ($_SESSION['config']['gestion_conges_exceptionnels'])
        {
            foreach($tab_type_conges_exceptionnels as $id_type_cong => $libelle)
            {
                echo '<th>'. _('divers_solde_maj') ." $libelle</th>\n";
                $nb_colonnes += 1;
            }
        }
        echo "<th></th>";
        $nb_colonnes += 1;
        if($_SESSION['config']['editions_papier'])
        {
            echo "<th></th>";
            $nb_colonnes += 1;
        }
        echo "</tr>\n";

        echo '</thead>';
        echo '<tbody>';

        /***********************************/
        // AFFICHAGE USERS

        /***********************************/
        // AFFICHAGE DE USERS DIRECTS DU RESP

        // Récup dans un tableau de tableau des informations de tous les users dont $_SESSION['userlogin'] est responsable
        $tab_all_users=recup_infos_all_users_du_resp($_SESSION['userlogin'],  $DEBUG);
        if( $DEBUG ) {echo "tab_all_users :<br>\n";  print_r($tab_all_users); echo "<br>\n"; }

        if(count($tab_all_users)==0) // si le tableau est vide (resp sans user !!) on affiche une alerte !
            echo "<tr align=\"center\"><td class=\"histo\" colspan=\"".$nb_colonnes."\">". _('resp_etat_aucun_user') ."</td></tr>\n" ;
        else
        {
            $i = true;
            foreach($tab_all_users as $current_login => $tab_current_user)
            {
                if($tab_current_user['is_active'] == "Y" || $_SESSION['config']['print_disable_users'] == 'TRUE')
                {
                    //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
                    $tab_conges=$tab_current_user['conges'];
                    $text_affich_user="<a class=\"action show\" href=\"resp_index.php?session=$session&onglet=traite_user&user_login=$current_login\" title=\""._('resp_etat_users_afficher')."\"><i class=\"fa fa-eye\"></i></a>" ;
                    $text_edit_papier="<a class=\"action edit\" href=\"../edition/edit_user.php?session=$session&user_login=$current_login\" target=\"_blank\" title=\""._('resp_etat_users_imprim')."\"><i class=\"fa fa-file-text\"></i></a>";

                    echo '<tr class="'.($i?'i':'p').'">';
                    echo "<td>".$tab_current_user['nom']."</td><td>".$tab_current_user['prenom']."</td><td>".$tab_current_user['quotite']."%</td>";
                    foreach($tab_type_cong as $id_conges => $libelle)
                    {
                        echo "<td>".$tab_conges[$libelle]['nb_an'].'</td>';
                        echo "<td>".$tab_conges[$libelle]['solde'].'</td>';
                    }
                    if ($_SESSION['config']['gestion_conges_exceptionnels'])
                    {
                        foreach($tab_type_conges_exceptionnels as $id_type_cong => $libelle)
                        {
                            echo "<td>".$tab_conges[$libelle]['solde'].'</td>';
                        }
                    }
                    echo "<td>$text_affich_user</td>\n";
                    if($_SESSION['config']['editions_papier'])
                        echo "<td>$text_edit_papier</td>";
                    echo "</tr>\n";
                    $i = !$i;
                }
            }
        }

        /***********************************/
        // AFFICHAGE DE USERS DONT LE RESP EST GRAND RESP

        if($_SESSION['config']['double_validation_conges'])
        {
            // Récup dans un tableau de tableau des informations de tous les users dont $_SESSION['userlogin'] est GRAND responsable
            $tab_all_users_2=recup_infos_all_users_du_grand_resp($_SESSION['userlogin'],  $DEBUG);

            if( $DEBUG ) {echo "tab_all_users_2 :<br>\n";  print_r($tab_all_users_2); echo "<br>\n"; }

            $compteur=0;  // compteur de ligne a afficher en dessous (dés que passe à 1 : on affiche une ligne de titre)

            $i = true;
            foreach($tab_all_users_2 as $current_login_2 => $tab_current_user_2)
            {
                if( !array_key_exists($current_login_2, $tab_all_users) ) // si le user n'est pas déjà dans le tableau précédent (deja affiché)
                {
                    $compteur++;
                    if($compteur==1)  // alors on affiche une ligne de titre
                    {
                        $nb_colspan=9;
                        if ($_SESSION['config']['gestion_conges_exceptionnels'])
                            $nb_colspan=10;

                        echo "<tr align=\"center\"><td class=\"histo\" style=\"background-color: #CCC;\" colspan=\"$nb_colonnes\"><i>". _('resp_etat_users_titre_double_valid') ."</i></td></tr>\n";
                    }

                    //tableau de tableaux les nb et soldes de conges d'un user (indicé par id de conges)
                    $tab_conges_2=$tab_current_user_2['conges'];

                    $text_affich_user="<a class=\"action show\" href=\"resp_index.php?session=$session&onglet=traite_user&user_login=$current_login_2\" title=\"". _('resp_etat_users_afficher') ."\"><i class=\"fa fa-eye\"></i></a>" ;
                    $text_edit_papier="<a class=\"action print\" href=\"../edition/edit_user.php?session=$session&user_login=$current_login_2\" target=\"_blank\" title=\""._('resp_etat_users_imprim')."\"><i class=\"fa fa-file-text\"></i></a>";
                    echo '<tr class="'.($i?'i':'p').'">';
                    echo "<td>".$tab_current_user_2['nom']."</td><td>".$tab_current_user_2['prenom']."</td><td>".$tab_current_user_2['quotite']."%</td>";
                    foreach($tab_type_cong as $id_conges => $libelle)
                    {
                        echo "<td>".$tab_conges_2[$libelle]['nb_an']."</td><td>".$tab_conges_2[$libelle]['solde'].'</td>';
                    }
                    if ($_SESSION['config']['gestion_conges_exceptionnels'])
                    {
                        foreach($tab_type_conges_exceptionnels as $id_type_cong => $libelle)
                        {
                            echo "<td>".$tab_conges_2[$libelle]['solde'].'</td>';
                        }
                    }
                    echo "<td>$text_affich_user</td>\n";
                    if($_SESSION['config']['editions_papier'])
                        echo "<td>$text_edit_papier</td>";
                    echo "</tr>\n";
                    $i = !$i;
                }
            }

        }

        echo '</tbody>';
        echo '</table>';
    }

    public static function new_conges($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type_id,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id();

        //conversion des dates
        $new_debut = convert_date($new_debut);
        $new_fin = convert_date($new_fin);

        // verif validité des valeurs saisies
        $valid=verif_saisie_new_demande($new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment);

        if( $DEBUG ) { echo "verif_saisie_new_demande resp_traite_user : $valid<br>\n"; }
        if($valid)
        {
            echo "$user_login---$new_debut _ $new_demi_jour_deb---$new_fin _ $new_demi_jour_fin---$new_nb_jours---$new_comment---$new_type_id<br>\n";

            // recup dans un tableau de tableau les infos des types de conges et absences
            $tab_tout_type_abs = recup_tableau_tout_types_abs( $DEBUG);

            /**********************************/
            /* insert dans conges_periode     */
            /**********************************/
            $new_etat="ok";
            $result=insert_dans_periode($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type_id, $new_etat, 0, $DEBUG);

            /************************************************/
            /* UPDATE table "conges_solde_user" (jours restants) */
            // on retranche les jours seulement pour des conges pris (pas pour les absences)
            // donc seulement si le type de l'absence qu'on annule est un "conges"
            if(isset($tab_tout_type_abs[$new_type_id]['type']) && $tab_tout_type_abs[$new_type_id]['type']=="conges")
            {
                $user_nb_jours_pris_float=(float) $new_nb_jours ;
                soustrait_solde_et_reliquat_user($user_login, "", $user_nb_jours_pris_float, $new_type_id, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin , $DEBUG);
            }
            $comment_log = "saisie conges par le responsable pour $user_login ($new_nb_jours jour(s)) type_conges = $new_type_id ( de $new_debut $new_demi_jour_deb a $new_fin $new_demi_jour_fin) ($new_comment)";
            log_action(0, "", $user_login, $comment_log,  $DEBUG);

            if($result)
                echo  _('form_modif_ok') ."<br><br> \n";
            else
                echo  _('form_modif_not_ok') ."<br><br> \n";
        }
        else
        {
            echo  _('resp_traite_user_valeurs_not_ok') ."<br><br> \n";
        }

        /* APPEL D'UNE AUTRE PAGE */
        echo "<form action=\"$PHP_SELF?session=$session&onglet=traite_user&user_login=$user_login\" method=\"POST\"> \n";
        echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_retour') ."\">\n";
        echo "</form> \n";
    }

    public static function traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id();

        // recup dans un tableau de tableau les infos des types de conges et absences
        $tab_tout_type_abs = recup_tableau_tout_types_abs( $DEBUG);

        while($elem_tableau = each($tab_radio_traite_demande))
        {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris=$champs[1];
            $user_nb_jours_pris_float=(float) $user_nb_jours_pris ;
            $value_type_abs_id=$champs[2];
            $date_deb=$champs[3];
            $demi_jour_deb=$champs[4];
            $date_fin=$champs[5];
            $demi_jour_fin=$champs[6];
            $reponse=$champs[7];
            //      $value_traite=$champs[3];

            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;
            if( $DEBUG ) { echo "<br><br>conges numero :$numero --- User_login : $user_login --- nb de jours : $user_nb_jours_pris --->$value_traite<br>" ; }

            if($reponse == "ACCEPTE") // acceptation definitive d'un conges
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat="ok", p_date_traitement=NOW() WHERE p_num="'.\includes\SQL::quote($numero_int).'";';
                $ReqLog1 = \includes\SQL::query($sql1);

                // Log de l'action
                log_action($numero_int,"ok", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $value_traite",  $DEBUG);

                /* UPDATE table "conges_solde_user" (jours restants) */
                // on retranche les jours seulement pour des conges pris (pas pour les absences)
                // donc seulement si le type de l'absence qu'on accepte est un "conges"
                if( $DEBUG ) { echo "type_abs = ".$tab_tout_type_abs[$value_type_abs_id]['type']."<br>\n" ; }
                if(($tab_tout_type_abs[$value_type_abs_id]['type']=="conges")||($tab_tout_type_abs[$value_type_abs_id]['type']=="conges_exceptionnels"))
                {
                    soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris_float, $value_type_abs_id, $date_deb, $demi_jour_deb, $date_fin, $demi_jour_fin, $DEBUG);
                }

                //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                if($_SESSION['config']['mail_valid_conges_alerte_user'])
                    alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "accept_conges",  $DEBUG);
            }
            elseif($reponse == "VALID") // première validation dans le cas d'une double validation
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat="valid", p_date_traitement=NOW() WHERE p_num="'.\includes\SQL::quote($numero_int).'";';
                $ReqLog1 = \inclusionSQL::query($sql1);

                // Log de l'action
                log_action($numero_int,"valid", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $value_traite",  $DEBUG);

                //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                if($_SESSION['config']['mail_valid_conges_alerte_user'])
                    alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "valid_conges",  $DEBUG);
            }
            elseif($reponse == "REFUSE") // refus d'un conges
            {
                // recup di motif de refus
                $motif_refus=addslashes($tab_text_refus[$numero_int]);
                //$sql3 = "UPDATE conges_periode SET p_etat=\"refus\" WHERE p_num=$numero_int" ;
                $sql3 = 'UPDATE conges_periode SET p_etat="refus", p_motif_refus=\''.$motif_refus.'\', p_date_traitement=NOW() WHERE p_num="'. \includes\SQL::quote($numero_int).'";';
                $ReqLog3 = \includes\SQL::query($sql3);

                // Log de l'action
                log_action($numero_int,"refus", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $value_traite",  $DEBUG);

                //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                if($_SESSION['config']['mail_refus_conges_alerte_user'])
                    alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "refus_conges",  $DEBUG);
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input type=\"hidden\" name=\"onglet\" value=\"traite_user\">\n";
            echo "<input type=\"hidden\" name=\"user_login\" value=\"$user_login\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo  _('form_modif_ok') ."<br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session&user_login=$user_login\">";
        }
    }

    public static function annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id() ;

        // recup dans un tableau de tableau les infos des types de conges et absences
        $tab_tout_type_abs = recup_tableau_tout_types_abs( $DEBUG);

        while($elem_tableau = each($tab_checkbox_annule))
        {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris=$champs[1];
            $user_nb_jours_pris_float=(float) $user_nb_jours_pris ;
            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;
            $user_type_abs_id=$champs[2];

            $motif_annul=addslashes($tab_text_annul[$numero_int]);

            if( $DEBUG ) { echo "<br><br>conges numero :$numero ---> login : $user_login --- nb de jours : $user_nb_jours_pris_float --- type : $user_type_abs_id ---> ANNULER <br>"; }

            /* UPDATE table "conges_periode" */
            $sql1 = 'UPDATE conges_periode SET p_etat="annul", p_motif_refus="'. \includes\SQL::quote($motif_annul).'", p_date_traitement=NOW() WHERE p_num="'. \includes\SQL::quote($numero_int).'" ';
            $ReqLog1 = \includes\SQL::query($sql1);

            // Log de l'action
            log_action($numero_int,"annul", $user_login, "annulation conges $numero ($user_login) ($user_nb_jours_pris jours)",  $DEBUG);

            /* UPDATE table "conges_solde_user" (jours restants) */
            // on re-crédite les jours seulement pour des conges pris (pas pour les absences)
            // donc seulement si le type de l'absence qu'on annule est un "conges"
            if($tab_tout_type_abs[$user_type_abs_id]['type']=="conges")
            {
                $sql2 = 'UPDATE conges_solde_user SET su_solde = su_solde+"'. \includes\SQL::quote($user_nb_jours_pris_float).'" WHERE su_login="'. \includes\SQL::quote($user_login).'" AND su_abs_idi="'. \includes\SQL::quote($user_type_abs_id).'";';
                //echo($sql2."<br>");
                $ReqLog2 = \includes\SQL::query($sql2);
            }

            //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
            if($_SESSION['config']['mail_annul_conges_alerte_user'])
                alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "annul_conges",  $DEBUG);
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input type=\"hidden\" name=\"onglet\" value=\"traite_user\">\n";
            echo "<input type=\"hidden\" name=\"user_login\" value=\"$user_login\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo  _('form_modif_ok') ."<br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session&user_login=$user_login\">";
        }
    }

    //affiche l'état des conges du user (avec le formulaire pour le responsable)
    public static function affiche_etat_conges_user_for_resp($user_login, $year_affichage, $tri_date, $onglet ,$DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id() ;

        // affichage de l'année et des boutons de défilement
        $year_affichage_prec = $year_affichage-1 ;
        $year_affichage_suiv = $year_affichage+1 ;
        echo "<div class=\"calendar-nav\">\n";
        echo "<ul>\n";
        echo "<li><a class=\"action previous\" href=\"$PHP_SELF?session=$session&onglet=traite_user&user_login=$user_login&year_affichage=$year_affichage_prec\"><i class=\"fa fa-chevron-left\"></i></a></li>\n";
        echo "<li class=\"current-year\">$year_affichage</li>";
        echo "<li><a class=\"action next\" href=\"$PHP_SELF?session=$session&onglet=traite_user&user_login=$user_login&year_affichage=$year_affichage_suiv\"><i class=\"fa fa-chevron-right\"></i></a></li>\n";
        echo "</ul>\n";
        echo "</div>\n";

        echo "<h2>". _('resp_traite_user_etat_conges') ." $year_affichage</h2>\n";

        // Récupération des informations de speriodes de conges/absences
        $sql3 = "SELECT p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_etat, p_motif_refus, p_date_demande, p_date_traitement, p_num FROM conges_periode " .
            "WHERE p_login = '$user_login' " .
            "AND p_etat !='demande' " .
            "AND p_etat !='valid' " .
            "AND (p_date_deb LIKE '$year_affichage%' OR p_date_fin LIKE '$year_affichage%') ";
        if($tri_date=="descendant")
            $sql3=$sql3." ORDER BY p_date_deb DESC ";
        else
            $sql3=$sql3." ORDER BY p_date_deb ASC ";

        $ReqLog3 = \includes\SQL::query($sql3);

        $count3=$ReqLog3->num_rows;
        if($count3==0)
        {
            echo "<b>". _('resp_traite_user_aucun_conges') ."</b><br><br>\n";
        }
        else
        {
            // recup dans un tableau de tableau les infos des types de conges et absences
            $tab_types_abs = recup_tableau_tout_types_abs( $DEBUG) ;

            // AFFICHAGE TABLEAU
            echo "<form action=\"$PHP_SELF?session=$session&onglet=traite_user\" method=\"POST\"> \n";
            //echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"80%\">\n";
            echo "<table class=\"table table-hover table-responsive table-condensed table-striped\">\n";
            echo "<thead>";
            echo "<tr align=\"center\">\n";
            echo " <th>\n";
            // echo " <a href=\"$PHP_SELF?session=$session&user_login=$user_login&onglet=$onglet&tri_date=descendant\"><img src=\"". TEMPLATE_PATH ."img/1downarrow-16x16.png\" width=\"16\" height=\"16\" border=\"0\" title=\"trier\"></a>\n";
            echo " ". _('divers_debut_maj_1') ." \n";
            // echo " <a href=\"$PHP_SELF?session=$session&user_login=$user_login&onglet=$onglet&tri_date=ascendant\"><img src=\"". TEMPLATE_PATH ."img/1uparrow-16x16.png\" width=\"16\" height=\"16\" border=\"0\" title=\"trier\"></a>\n";
            echo " </th>\n";
            echo " <th>". _('divers_fin_maj_1') ."</th>\n";
            echo " <th>". _('divers_nb_jours_pris_maj_1') ."</th>\n";
            echo " <th>". _('divers_comment_maj_1') ."</th>\n";
            echo " <th>". _('divers_type_maj_1') ."</th>\n";
            echo " <th>". _('divers_etat_maj_1') ."</th>\n";
            echo " <th>". _('resp_traite_user_annul') ."</th>\n";
            echo " <th>". _('resp_traite_user_motif_annul') ."</th>\n";
            if($_SESSION['config']['affiche_date_traitement'])
            {
                echo "<th>". _('divers_date_traitement') ."</th>\n" ;
            }
            echo "</tr>\n";
            echo "</thead>";
            echo "<tbody>";
            $tab_checkbox=array();
            $i = true;
            while ($resultat3 = $ReqLog3->fetch_array())
            {
                $sql_login=$resultat3["p_login"] ;
                $sql_date_deb=eng_date_to_fr($resultat3["p_date_deb"]) ;
                $sql_demi_jour_deb=$resultat3["p_demi_jour_deb"] ;
                if($sql_demi_jour_deb=="am")
                    $demi_j_deb =  _('divers_am_short') ;
                else
                    $demi_j_deb =  _('divers_pm_short') ;
                $sql_date_fin=eng_date_to_fr($resultat3["p_date_fin"]) ;
                $sql_demi_jour_fin=$resultat3["p_demi_jour_fin"] ;
                if($sql_demi_jour_fin=="am")
                    $demi_j_fin =  _('divers_am_short') ;
                else
                    $demi_j_fin =  _('divers_pm_short') ;
                $sql_nb_jours=affiche_decimal($resultat3["p_nb_jours"]) ;
                $sql_commentaire=$resultat3["p_commentaire"] ;
                $sql_type=$resultat3["p_type"] ;
                $sql_etat=$resultat3["p_etat"] ;
                $sql_motif_refus=$resultat3["p_motif_refus"] ;
                $sql_p_date_demande = $resultat3["p_date_demande"];
                $sql_p_date_traitement = $resultat3["p_date_traitement"];
                $sql_num=$resultat3["p_num"] ;

                if(($sql_etat=="annul") || ($sql_etat=="refus") || ($sql_etat=="ajout"))
                {
                    $casecocher1="";
                    if($sql_etat=="refus")
                    {
                        if($sql_motif_refus=="")
                            $sql_motif_refus =  _('divers_inconnu')  ;
                        //$text_annul="<i>motif du refus : $sql_motif_refus</i>";
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    }
                    elseif($sql_etat=="annul")
                    {
                        if($sql_motif_refus=="")
                            $sql_motif_refus =  _('divers_inconnu')  ;
                        //$text_annul="<i>motif de l'annulation : $sql_motif_refus</i>";
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    }
                    elseif($sql_etat=="ajout")
                    {
                        $text_annul="&nbsp;";
                    }
                }
                else
                {
                    $casecocher1=sprintf("<input type=\"checkbox\" name=\"tab_checkbox_annule[$sql_num]\" value=\"$sql_login--$sql_nb_jours--$sql_type--ANNULE\">");
                    $text_annul="<input type=\"text\" name=\"tab_text_annul[$sql_num]\" size=\"20\" max=\"100\">";
                }

                echo '<tr class="'.($i?'i':'p').'">';
                echo "<td>$sql_date_deb _ $demi_j_deb</td>\n";
                echo "<td>$sql_date_fin _ $demi_j_fin</td>\n";
                echo "<td>$sql_nb_jours</td>\n";
                echo "<td>$sql_commentaire</td>\n";
                echo "<td>".$tab_types_abs[$sql_type]['libelle']."</td>\n";
                echo "<td>";
                if($sql_etat=="refus")
                    echo  _('divers_refuse') ;
                elseif($sql_etat=="annul")
                    echo  _('divers_annule') ;
                else
                    echo "$sql_etat";
                echo "</td>\n";
                echo "<td>$casecocher1</td>\n";
                echo "<td>$text_annul</td>\n";
                if($_SESSION['config']['affiche_date_traitement'])
                {
                    if(empty($sql_p_date_traitement))
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_p_date_demande<br>". _('divers_traitement') ." : pas traité</td>\n" ;
                    else
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_p_date_demande<br>". _('divers_traitement') ." : $sql_p_date_traitement</td>\n" ;
                }
                echo "</tr>\n";
                $i = !$i;
            }
            echo "</tbody>";
            echo "</table>\n\n";

            echo "<input type=\"hidden\" name=\"user_login\" value=\"$user_login\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_submit') ."\">\n";
            echo "</form> \n";
        }
    }

    //affiche l'état des demande en attente de 2ieme validation du user (avec le formulaire pour le responsable)
    public static function affiche_etat_demande_2_valid_user_for_resp($user_login,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id() ;

        // Récupération des informations
        $sql2 = "SELECT p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement, p_num " .
            "FROM conges_periode " .
            "WHERE p_login = '$user_login' AND p_etat ='valid' ORDER BY p_date_deb";
        $ReqLog2 = \includes\SQL::query($sql2);

        $count2=$ReqLog2->num_rows;
        if($count2==0)
        {
            echo "<b>". _('resp_traite_user_aucune_demande') ."</b><br><br>\n";
        }
        else
        {
            // recup dans un tableau des types de conges
            $tab_type_all_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            echo " <form action=\"$PHP_SELF?session=$session&onglet=traite_user\" method=\"POST\"> \n";
            //echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"80%\">\n";
            echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\">\n";
            echo "<thead>";
            echo "<tr align=\"center\">\n";
            echo "<th>". _('divers_debut_maj_1') ."</th>\n";
            echo "<th>". _('divers_fin_maj_1') ."</th>\n";
            echo "<th>". _('divers_nb_jours_pris_maj_1') ."</th>\n";
            echo "<th>". _('divers_comment_maj_1') ."</th>\n";
            echo "<th>". _('divers_type_maj_1') ."</th>\n";
            echo "<th>". _('divers_accepter_maj_1') ."</th>\n";
            echo "<th>". _('divers_refuser_maj_1') ."</th>\n";
            echo "<th>". _('resp_traite_user_motif_refus') ."</th>\n";
            if($_SESSION['config']['affiche_date_traitement'])
            {
                echo "<th>". _('divers_date_traitement') ."</th>\n" ;
            }
            echo "</tr>\n";
            echo "</thead>";
            echo "<tbody>";

            $i = true;
            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array())
            {
                $sql_date_deb = $resultat2["p_date_deb"];
                $sql_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]) ;
                $sql_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                if($sql_demi_jour_deb=="am")
                    $demi_j_deb =  _('divers_am_short') ;
                else
                    $demi_j_deb =  _('divers_pm_short') ;
                $sql_date_fin = $resultat2["p_date_fin"];
                $sql_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]) ;
                $sql_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                if($sql_demi_jour_fin=="am")
                    $demi_j_fin =  _('divers_am_short') ;
                else
                    $demi_j_fin =  _('divers_pm_short') ;
                $sql_nb_jours=affiche_decimal($resultat2["p_nb_jours"]) ;
                $sql_commentaire=$resultat2["p_commentaire"] ;
                $sql_type=$resultat2["p_type"] ;
                $sql_date_demande = $resultat2["p_date_demande"];
                $sql_date_traitement = $resultat2["p_date_traitement"];
                $sql_num=$resultat2["p_num"] ;

                // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                $chaine_bouton_radio = "$user_login--$sql_nb_jours--$sql_type--$sql_date_deb--$sql_demi_jour_deb--$sql_date_fin--$sql_demi_jour_fin";


                $casecocher1 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                $casecocher2 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--REFUSE\">";
                $text_refus  = "<input type=\"text\" name=\"tab_text_refus[$sql_num]\" size=\"20\" max=\"100\">";

                echo '<tr class="'.($i?'i':'p').'">';
                echo "<td>$sql_date_deb_fr _ $demi_j_deb</td>\n";
                echo "<td>$sql_date_fin_fr _ $demi_j_fin</td>\n";
                echo "<td>$sql_nb_jours</td>\n";
                echo "<td>$sql_commentaire</td>\n";
                echo "<td>".$tab_type_all_abs[$sql_type]['libelle']."</td>\n";
                echo "<td>$casecocher1</td>\n";
                echo "<td>$casecocher2</td>\n";
                echo "<td>$text_refus</td>\n";
                if($_SESSION['config']['affiche_date_traitement'])
                {
                    if(empty($sql_date_traitement))
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_date_demande<br>". _('divers_traitement') ." : pas traité</td>\n" ;
                    else
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_date_demande<br>". _('divers_traitement') ." : $sql_date_traitement</td>\n" ;
                }
                echo "</tr>\n";
                $i = !$i;
            }
            echo "</tbody>";
            echo "</table>\n\n";

            echo "<input type=\"hidden\" name=\"user_login\" value=\"$user_login\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_submit') ."\">  &nbsp;&nbsp;&nbsp;&nbsp;  <input type=\"reset\" value=\"". _('form_cancel') ."\">\n";
            echo " </form> \n";
        }
    }

    //affiche l'état des demandes du user (avec le formulaire pour le responsable)
    public static function affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp,  $DEBUG=FALSE)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id();

        // Récupération des informations
        $sql2 = "SELECT p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement, p_num " .
            "FROM conges_periode " .
            "WHERE p_login = '$user_login' AND p_etat ='demande' ".
            "ORDER BY p_date_deb";
        $ReqLog2 = \includes\SQL::query($sql2);

        $count2=$ReqLog2->num_rows;
        if($count2==0)
        {
            echo "<p><strong>". _('resp_traite_user_aucune_demande') ."</strong></p>\n";
        }
        else
        {
            // recup dans un tableau des types de conges
            $tab_type_all_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            echo " <form action=\"$PHP_SELF?session=$session&onglet=traite_user\" method=\"POST\"> \n";
            //echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"80%\">\n";
            echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\">\n";
            echo "<tr align=\"center\">\n";
            echo "<td>". _('divers_debut_maj_1') ."</td>\n";
            echo "<td>". _('divers_fin_maj_1') ."</td>\n";
            echo "<td>". _('divers_nb_jours_pris_maj_1') ."</td>\n";
            echo "<td>". _('divers_comment_maj_1') ."</td>\n";
            echo "<td>". _('divers_type_maj_1') ."</td>\n";
            echo "<td>". _('divers_accepter_maj_1') ."</td>\n";
            echo "<td>". _('divers_refuser_maj_1') ."</td>\n";
            echo "<td>". _('resp_traite_user_motif_refus') ."</td>\n";
            if($_SESSION['config']['affiche_date_traitement'])
            {
                echo "<td>". _('divers_date_traitement') ."</td>\n" ;
            }
            echo "</tr>\n";

            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array())
            {
                $sql_date_deb = $resultat2["p_date_deb"];
                $sql_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]) ;
                $sql_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                if($sql_demi_jour_deb=="am")
                    $demi_j_deb =  _('divers_am_short') ;
                else
                    $demi_j_deb =  _('divers_pm_short') ;
                $sql_date_fin = $resultat2["p_date_fin"];
                $sql_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]) ;
                $sql_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                if($sql_demi_jour_fin=="am")
                    $demi_j_fin =  _('divers_am_short') ;
                else
                    $demi_j_fin =  _('divers_pm_short') ;
                $sql_nb_jours=affiche_decimal($resultat2["p_nb_jours"]) ;
                $sql_commentaire=$resultat2["p_commentaire"] ;
                $sql_type=$resultat2["p_type"] ;
                $sql_date_demande = $resultat2["p_date_demande"];
                $sql_date_traitement = $resultat2["p_date_traitement"];
                $sql_num=$resultat2["p_num"] ;

                // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                $chaine_bouton_radio = "$user_login--$sql_nb_jours--$sql_type--$sql_date_deb--$sql_demi_jour_deb--$sql_date_fin--$sql_demi_jour_fin";

                // si le user fait l'objet d'une double validation on a pas le meme resultat sur le bouton !
                if($tab_user['double_valid'] == "Y")
                {
                    /*******************************/
                    /* verif si le resp est grand_responsable pour ce user*/
                    if(in_array($_SESSION['userlogin'], $tab_grd_resp)) // si user_login est dans le tableau des grand responsable
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                    else
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--VALID\">";
                }
                else
                    $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";

                $boutonradio2 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--REFUSE\">";

                $text_refus  = "<input type=\"text\" name=\"tab_text_refus[$sql_num]\" size=\"20\" max=\"100\">";

                echo "<tr align=\"center\">\n";
                echo "<td>$sql_date_deb_fr _ $demi_j_deb</td>\n";
                echo "<td>$sql_date_fin_fr _ $demi_j_fin</td>\n";
                echo "<td>$sql_nb_jours</td>\n";
                echo "<td>$sql_commentaire</td>\n";
                echo "<td>".$tab_type_all_abs[$sql_type]['libelle']."</td>\n";
                echo "<td>$boutonradio1</td>\n";
                echo "<td>$boutonradio2</td>\n";
                echo "<td>$text_refus</td>\n";
                echo "<td>$sql_date_demande</td>\n";

                if($_SESSION['config']['affiche_date_traitement'])
                {
                    if(empty($sql_date_traitement))
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_date_demande<br>". _('divers_traitement') ." : pas traité</td>\n" ;
                    else
                        echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_date_demande<br>". _('divers_traitement') ." : $sql_date_traitement</td>\n" ;
                }

                echo "</tr>\n";
            }
            echo "</table>\n\n";

            echo "<input type=\"hidden\" name=\"user_login\" value=\"$user_login\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_submit') ."\">  &nbsp;&nbsp;&nbsp;&nbsp;  <input type=\"reset\" value=\"". _('form_cancel') ."\">\n";
            echo " </form> \n";
        }
    }

    public static function affichage($user_login,  $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date,  $DEBUG)
    {
        $PHP_SELF=$_SERVER['PHP_SELF']; ;
        $session=session_id();

        // on initialise le tableau global des jours fériés s'il ne l'est pas déjà :
        if(!isset($_SESSION["tab_j_feries"]))
        {
            init_tab_jours_feries();
            //print_r($GLOBALS["tab_j_feries"]);   // verif DEBUG
        }

        /********************/
        /* Récupération des informations sur le user : */
        /********************/
        $list_group_dbl_valid_du_resp = get_list_groupes_double_valid_du_resp($_SESSION['userlogin'],  $DEBUG);
        $tab_user=array();
        $tab_user = recup_infos_du_user($user_login, $list_group_dbl_valid_du_resp,  $DEBUG);
        if( $DEBUG ) { echo"tab_user =<br>\n"; print_r($tab_user); echo "<br>\n"; }

        $list_all_users_du_resp=get_list_all_users_du_resp($_SESSION['userlogin'],  $DEBUG);
        if( $DEBUG ) { echo"list_all_users_du_resp = $list_all_users_du_resp<br>\n"; }

        // recup des grd resp du user
        $tab_grd_resp=array();
        if($_SESSION['config']['double_validation_conges'])
        {
            get_tab_grd_resp_du_user($user_login, $tab_grd_resp,  $DEBUG);
            if( $DEBUG ) { echo"tab_grd_resp =<br>\n"; print_r($tab_grd_resp); echo "<br>\n"; }
        }

        /********************/
        /* Titre */
        /********************/
        echo "<h1>".$tab_user['prenom']." ".$tab_user['nom']."</h1>\n\n";


        /********************/
        /* Bilan des Conges */
        /********************/
        // AFFICHAGE TABLEAU
        // affichage du tableau récapitulatif des solde de congés d'un user
        affiche_tableau_bilan_conges_user($user_login);
        echo "<hr/>\n";

        /*************************/
        /* SAISIE NOUVEAU CONGES */
        /*************************/
        // dans le cas ou les users ne peuvent pas saisir de demande, le responsable saisi les congès :
        if( !$_SESSION['config']['user_saisie_demande'] || $_SESSION['config']['resp_saisie_mission'] )
        {

            // si les mois et année ne sont pas renseignés, on prend ceux du jour
            if($year_calendrier_saisie_debut==0)
                $year_calendrier_saisie_debut=date("Y");
            if($mois_calendrier_saisie_debut==0)
                $mois_calendrier_saisie_debut=date("m");
            if($year_calendrier_saisie_fin==0)
                $year_calendrier_saisie_fin=date("Y");
            if($mois_calendrier_saisie_fin==0)
                $mois_calendrier_saisie_fin=date("m");
            if( $DEBUG ) { echo "$mois_calendrier_saisie_debut  $year_calendrier_saisie_debut  -  $mois_calendrier_saisie_fin  $year_calendrier_saisie_fin<br>\n"; }

            echo "<h2>". _('resp_traite_user_new_conges') ."</h2>\n";

            //affiche le formulaire de saisie d'une nouvelle demande de conges ou d'un  nouveau conges
            $onglet = "traite_user";
            saisie_nouveau_conges2($user_login, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $onglet);

            echo "<hr/>\n";
        }

        /*********************/
        /* Etat des Demandes */
        /*********************/
        if($_SESSION['config']['user_saisie_demande'])
        {
            //verif si le user est bien un user du resp (et pas seulement du grand resp)
            if(strstr($list_all_users_du_resp, "'$user_login'")!=FALSE)
            {
                echo "<h2>". _('resp_traite_user_etat_demandes') ."</h2>\n";

                //affiche l'état des demandes du user (avec le formulaire pour le responsable)
                \responsable\Fonctions::affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp,  $DEBUG);

                echo "<hr/>\n";
            }
        }

        /*********************/
        /* Etat des Demandes en attente de 2ieme validation */
        /*********************/
        if($_SESSION['config']['double_validation_conges'])
        {
            /*******************************/
            /* verif si le resp est grand_responsable pour ce user*/

            if(in_array($_SESSION['userlogin'], $tab_grd_resp)) // si resp_login est dans le tableau
            {
                echo "<h2>". _('resp_traite_user_etat_demandes_2_valid') ."</h2>\n";

                //affiche l'état des demande en attente de 2ieme valid du user (avec le formulaire pour le responsable)
                \responsable\Fonctions::affiche_etat_demande_2_valid_user_for_resp($user_login,  $DEBUG);

                echo "<hr/>\n";
            }
        }

        /*******************/
        /* Etat des Conges */
        /*******************/
        //affiche l'état des conges du user (avec le formulaire pour le responsable)
        $onglet = "traite_user";
        \responsable\Fonctions::affiche_etat_conges_user_for_resp($user_login,  $year_affichage, $tri_date, $onglet, $DEBUG);

    }
    
    /**
     * Encapsule le comportement du module de gestion des congés des utilisateurs
     *
     * @return void
     * @access public
     * @static
     */
    public static function traiteUserModule($DEBUG = false)
    {
        //var pour resp_traite_user.php
        $user_login   = getpost_variable('user_login') ;
        $year_calendrier_saisie_debut = getpost_variable('year_calendrier_saisie_debut', 0) ;
        $mois_calendrier_saisie_debut = getpost_variable('mois_calendrier_saisie_debut', 0) ;
        $year_calendrier_saisie_fin = getpost_variable('year_calendrier_saisie_fin', 0) ;
        $mois_calendrier_saisie_fin = getpost_variable('mois_calendrier_saisie_fin', 0) ;
        $tri_date = getpost_variable('tri_date', "ascendant") ;
        $tab_checkbox_annule = getpost_variable('tab_checkbox_annule') ;
        $tab_radio_traite_demande = getpost_variable('tab_radio_traite_demande') ;
        $tab_text_refus = getpost_variable('tab_text_refus') ;
        $tab_text_annul = getpost_variable('tab_text_annul') ;
        $new_demande_conges = getpost_variable('new_demande_conges', 0) ;
        $new_debut = getpost_variable('new_debut') ;
        $new_demi_jour_deb = getpost_variable('new_demi_jour_deb') ;
        $new_fin = getpost_variable('new_fin') ;
        $new_demi_jour_fin = getpost_variable('new_demi_jour_fin') ;

        if($_SESSION['config']['disable_saise_champ_nb_jours_pris'])  // zone de texte en readonly et grisée
            $new_nb_jours = compter($user_login, '', $new_debut,  $new_fin, $new_demi_jour_deb, $new_demi_jour_fin, $comment,  $DEBUG);
        else
            $new_nb_jours = getpost_variable('new_nb_jours') ;

        $new_comment = getpost_variable('new_comment') ;
        $new_type = getpost_variable('new_type') ;
        $year_affichage = getpost_variable('year_affichage' , date("Y") );

        /*************************************/

        if ( !is_resp_of_user($_SESSION['userlogin'] , $user_login)) {
            redirect(ROOT_PATH . 'deconnexion.php');
            exit;
        }

        /************************************/


        // si une annulation de conges a été selectionée :
        if($tab_checkbox_annule!="")
        {
            \responsable\Fonctions::annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul,  $DEBUG);
        }
        // si le traitement des demandes a été selectionée :
        elseif($tab_radio_traite_demande!="")
        {
            \responsable\Fonctions::traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus,  $DEBUG);
        }
        // si un nouveau conges ou absence a été saisi pour un user :
        elseif($new_demande_conges==1)
        {
            \responsable\Fonctions::new_conges($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type,  $DEBUG);
        }
        else
        {
            \responsable\Fonctions::affichage($user_login,  $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date,  $DEBUG);
        }
    }

    public static function traite_all_demande_en_cours( $tab_bt_radio, $tab_text_refus, $DEBUG=FALSE) 
    {

        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id();

        while($elem_tableau = each($tab_bt_radio))
        {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris=$champs[1];
            $type_abs=$champs[2];   // id du type de conges demandé
            $date_deb=$champs[3];
            $demi_jour_deb=$champs[4];
            $date_fin=$champs[5];
            $demi_jour_fin=$champs[6];
            $reponse=$champs[7];

            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;
            echo "$numero---$user_login---$user_nb_jours_pris---$reponse<br>\n";

            /* Modification de la table conges_periode */
            if(strcmp($reponse, "VALID")==0)
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat=\'valid\', p_date_traitement=NOW() WHERE p_num="'. \includes\SQL::quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';
                /* On valide l'UPDATE dans la table "conges_periode" ! */
                $ReqLog1 = \includes\SQL::query($sql1) ;
                if ($ReqLog1 && SQL::getVar('affected_rows') ) {

                    // Log de l'action
                    log_action($numero_int, "valid", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $reponse",  $DEBUG);

                    //envoi d'un mail d'alerte au user et au responsable du resp (pour double validation) (si demandé dans config de libertempo)
                    if($_SESSION['config']['mail_prem_valid_conges_alerte_user'])
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "valid_conges",  $DEBUG);
                }
            }

            if(strcmp($reponse, "OK")==0)
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat="ok", p_date_traitement=NOW() WHERE p_num="'.\includes\SQL::quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';
                /* On valide l'UPDATE dans la table "conges_periode" ! */
                $ReqLog1 = \includes\SQL::query($sql1) ;
                if ($ReqLog1 && \includes\SQL::getVar('affected_rows') ) {

                    // Log de l'action
                    log_action($numero_int,"ok", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $reponse",  $DEBUG);

                    /* UPDATE table "conges_solde_user" (jours restants) */
                    soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris, $type_abs, $date_deb, $demi_jour_deb, $date_fin, $demi_jour_fin, $DEBUG);

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if($_SESSION['config']['mail_valid_conges_alerte_user'])
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "accept_conges",  $DEBUG);
                }
            }
            elseif(strcmp($reponse, "not_OK")==0)
            {
                // recup du motif de refus
                $motif_refus=addslashes($tab_text_refus[$numero_int]);
                $sql1 = 'UPDATE conges_periode SET p_etat=\'refus\', p_motif_refus=\''.$motif_refus.'\', p_date_traitement=NOW() WHERE p_num="'. \includes\SQL::quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';

                /* On valide l'UPDATE dans la table ! */
                $ReqLog1 = \includes\SQL::query($sql1) ;
                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {

                    // Log de l'action
                    log_action($numero_int,"refus", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : refus",  $DEBUG);


                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if($_SESSION['config']['mail_refus_conges_alerte_user'])
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "refus_conges",  $DEBUG);
                }
            }
        }

        if( $DEBUG )
        {
            echo "<form action=\"$PHP_SELF?sesssion=$session&onglet=traitement_demande\" method=\"POST\">\n" ;
            echo "<input type=\"hidden\" name=\"session\" value=\"$session\">\n";
            echo "<input class=\"btn\" type=\"submit\" value=\"". _('form_ok') ."\">\n";
            echo "</form>\n" ;
        }
        else
        {
            echo  _('form_modif_ok') ."<br><br> \n";
            /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
            echo "<META HTTP-EQUIV=REFRESH CONTENT=\"2; URL=$PHP_SELF?session=$session&onglet=traitement_demandes\">";
        }
    }

    public static function affiche_all_demandes_en_cours($tab_type_conges,  $DEBUG=FALSE) 
    {
        //$DEBUG=TRUE ;
        $PHP_SELF=$_SERVER['PHP_SELF'];
        $session=session_id() ;
        $count1=0;
        $count2=0;

        $tab_type_all_abs = recup_tableau_tout_types_abs();

        // recup du tableau des types de conges (seulement les conges exceptionnels)
        $tab_type_conges_exceptionnels=array();
        if ($_SESSION['config']['gestion_conges_exceptionnels']) 
            $tab_type_conges_exceptionnels=recup_tableau_types_conges_exceptionnels( $DEBUG);

        /*********************************/
        // Récupération des informations
        /*********************************/

        // Récup dans un tableau de tableau des informations de tous les users dont $_SESSION['userlogin'] est responsable
        $tab_all_users_du_resp=recup_infos_all_users_du_resp($_SESSION['userlogin'],  $DEBUG);
        if( $DEBUG ) { echo "tab_all_users_du_resp :<br>\n"; print_r($tab_all_users_du_resp); echo "<br><br>\n";}

        // si tableau des users du resp n'est pas vide
        if( count($tab_all_users_du_resp) !=0 )
        {
            // constitution de la liste (séparé par des virgules) des logins ...
            $list_users_du_resp="";
            foreach($tab_all_users_du_resp as $current_login => $tab_current_user)
            {
                if($list_users_du_resp=="")
                    $list_users_du_resp= "'$current_login'" ;
                else
                    $list_users_du_resp=$list_users_du_resp.", '$current_login'" ;
            }
        }


        // Récup dans un tableau de tableau des informations de tous les users dont $_SESSION['userlogin'] est GRAND responsable
        if( $_SESSION['config']['double_validation_conges'] )
        {
            $tab_all_users_du_grand_resp=recup_infos_all_users_du_grand_resp($_SESSION['userlogin'],  $DEBUG);

            // si tableau des users du grand resp n'est pas vide
            if( count($tab_all_users_du_grand_resp)!=0 )
            {
                // constitution de la liste (séparé par des virgules) des logins ...
                $list_users_du_grand_resp="";
                foreach($tab_all_users_du_grand_resp as $current_login => $tab_current_user)
                {
                    if($list_users_du_grand_resp=="")
                        $list_users_du_grand_resp= "'$current_login'" ;
                    else
                        $list_users_du_grand_resp=$list_users_du_grand_resp.", '$current_login'" ;
                }
            }
        }

        /*********************************/




        echo " <form action=\"$PHP_SELF?session=$session&onglet=traitement_demandes\" method=\"POST\"> \n" ;

        /*********************************/
        /* TABLEAU DES DEMANDES DES USERS DONT ON EST LE RESP */
        /*********************************/

        // si tableau des users du resp n'est pas vide
        if( count($tab_all_users_du_resp)!=0 )
        {

            // Récup des demandes en cours pour les users dont $_SESSION['userlogin'] est responsable :
            $sql1 = "SELECT p_num, p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement FROM conges_periode ";
            $sql1=$sql1." WHERE p_etat =\"demande\" ";
            if($_SESSION['config']['responsable_virtuel'])
                $sql1=$sql1." AND p_login != 'conges' ";
            else
                $sql1=$sql1." AND p_login IN ($list_users_du_resp)  ";
            $sql1=$sql1." ORDER BY p_num";

            $ReqLog1 = \includes\SQL::query($sql1) ;

            $count1=$ReqLog1->num_rows;
            if($count1!=0)
            {
                // AFFICHAGE TABLEAU DES DEMANDES EN COURS

                echo "<h3>". _('resp_traite_demandes_titre_tableau_1') ."</h3>\n" ;

                echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\">\n" ;
                echo "<thead>\n" ;
                echo "<tr>\n" ;
                echo "<th>". _('divers_nom_maj_1') ."<br>". _('divers_prenom_maj_1') ."</th>\n" ;
                echo "<th>". _('divers_quotite_maj_1') ."</th>" ;
                echo "<th>". _('divers_type_maj_1') ."</th>\n" ;
                echo "<th>". _('divers_debut_maj_1') ."</th>\n" ;
                echo "<th>". _('divers_fin_maj_1') ."</th>\n" ;
                echo "<th>". _('divers_comment_maj_1') ."</th>\n" ;
                echo "<th>". _('resp_traite_demandes_nb_jours') ."</th>";
                // foreach($tab_type_conges as $id_conges => $libelle)
                // {
                // 	echo "<th>". _('divers_solde_maj_1') ."<br>$libelle</th>" ;
                // }
                // if ($_SESSION['config']['gestion_conges_exceptionnels']) 
                // foreach($tab_type_conges_exceptionnels as $id_conges => $libelle)
                // {
                // 	echo "<th>". _('divers_solde_maj_1') ."<br>$libelle</th>" ;
                // }			
                echo "<th>". _('divers_solde') ."</th>\n" ;
                echo "<th>". _('divers_accepter_maj_1') ."</th>\n" ;
                echo "<th>". _('divers_refuser_maj_1') ."</th>\n" ;
                echo "<th>". _('resp_traite_demandes_attente') ."</th>\n" ;
                echo "<th>". _('resp_traite_demandes_motif_refus') ."</th>\n" ;
                if($_SESSION['config']['affiche_date_traitement'])
                {
                    echo "<th>". _('divers_date_traitement') ."</th>\n" ;
                }
                echo "</tr>\n";
                echo "</thead>\n" ;
                echo "<tbody>\n" ;

                $i = true;			
                $tab_bt_radio=array();
                while ($resultat1 = $ReqLog1->fetch_array()) 
                {
                    /** sur la ligne ,   **/
                    /** le 1er bouton radio est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--OK"> */
                    /**  et le 2ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--not_OK"> */
                    /**  et le 3ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--RIEN"> */

                    $sql_p_date_deb = $resultat1["p_date_deb"];
                    $sql_p_date_deb_fr = eng_date_to_fr($resultat1["p_date_deb"]);
                    $sql_p_demi_jour_deb=$resultat1["p_demi_jour_deb"] ;
                    if($sql_p_demi_jour_deb=="am") $demi_j_deb="matin";  else $demi_j_deb="après-midi";
                    $sql_p_date_fin = $resultat1["p_date_fin"];
                    $sql_p_date_fin_fr = eng_date_to_fr($resultat1["p_date_fin"]);
                    $sql_p_demi_jour_fin=$resultat1["p_demi_jour_fin"] ;
                    if($sql_p_demi_jour_fin=="am") $demi_j_fin="matin";  else $demi_j_fin="après-midi";
                    $sql_p_commentaire = $resultat1["p_commentaire"];
                    $sql_p_num = $resultat1["p_num"];
                    $sql_p_login = $resultat1["p_login"];
                    $sql_p_nb_jours = affiche_decimal($resultat1["p_nb_jours"]);
                    $sql_p_type = $resultat1["p_type"];
                    $sql_p_date_demande = $resultat1["p_date_demande"];
                    $sql_p_date_traitement = $resultat1["p_date_traitement"];

                    // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                    $chaine_bouton_radio = "$sql_p_login--$sql_p_nb_jours--$sql_p_type--$sql_p_date_deb--$sql_p_demi_jour_deb--$sql_p_date_fin--$sql_p_demi_jour_fin";

                    // si le user fait l'objet d'une double validation on a pas le meme resultat sur le bouton !
                    if($tab_all_users_du_resp[$sql_p_login]['double_valid'] == "Y")
                    {
                        // si on est a la fois resp et grand resp
                        if( (count($tab_all_users_du_grand_resp)!=0 ) && (array_key_exists($sql_p_login, $tab_all_users_du_grand_resp)) )
                            $boutonradio1="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--OK\">";
                        else  //on est QUe resp
                            $boutonradio1="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--VALID\">";
                    }
                    else
                        $boutonradio1="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--OK\">";

                    $boutonradio2="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--not_OK\">";
                    $boutonradio3="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--RIEN\" checked>";

                    $text_refus="<input class=\"form-control\" type=\"text\" name=\"tab_text_refus[$sql_p_num]\" size=\"20\" max=\"100\">";

                    echo '<tr class="'.($i?'i':'p').'">';
                    echo "<td><b>".$tab_all_users_du_resp[$sql_p_login]['nom']."</b><br>".$tab_all_users_du_resp[$sql_p_login]['prenom']."</td><td>".$tab_all_users_du_resp[$sql_p_login]['quotite']."%</td>";
                    echo "<td>".$tab_type_all_abs[$sql_p_type]['libelle']."</td>\n";	
                    echo "<td>$sql_p_date_deb_fr <span class=\"demi\">$demi_j_deb</span></td><td>$sql_p_date_fin_fr <span class=\"demi\">$demi_j_fin</span></td><td>$sql_p_commentaire</td><td><b>$sql_p_nb_jours</b></td>";
                    $tab_conges=$tab_all_users_du_resp[$sql_p_login]['conges']; 
                    echo "<td>".$tab_conges[$tab_type_all_abs[$sql_p_type]['libelle']]['solde']."</td>";
                    // foreach($tab_type_conges as $id_conges => $libelle)
                    // {
                    // 	echo "<td>".$tab_conges[$libelle]['solde']."</td>";
                    // }
                    // if ($_SESSION['config']['gestion_conges_exceptionnels']) 
                    // foreach($tab_type_conges_exceptionnels as $id_conges => $libelle)
                    // {
                    // 	echo "<td>".$tab_conges[$libelle]['solde']."</td>";
                    // }			
                    // echo "<td>".$tab_type_all_abs[$sql_p_type]['libelle']."</td>\n";			
                    echo "<td>$boutonradio1</td><td>$boutonradio2</td><td>$boutonradio3</td><td>$text_refus</td>\n";
                    if($_SESSION['config']['affiche_date_traitement'])
                    {
                        if($sql_p_date_demande == NULL)
                            echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_p_date_demande<br>". _('divers_traitement') ." : $sql_p_date_traitement</td>\n" ;
                        else
                            echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_p_date_demande<br>". _('divers_traitement') ." : pas traité</td>\n" ;
                    }

                    echo "</tr>\n" ;
                    $i = !$i;
                } // while
                echo "</tbody>\n" ;
                echo "</table>\n\n" ;
            } //if($count1!=0)
        } //if( count($tab_all_users_du_resp)!=0 )


        /*********************************/
        /* TABLEAU DES DEMANDES DES USERS DONT ON EST LE GRAND RESP */
        /*********************************/

        if($_SESSION['config']['double_validation_conges'])
        {

            // si tableau des users du grand resp n'est pas vide
            if( count($tab_all_users_du_grand_resp)!=0 )
            {

                // Récup des demandes en cours pour les users dont $_SESSION['userlogin'] est GRAND responsable :
                $sql2 = "SELECT p_num, p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement FROM conges_periode ";
                $sql2=$sql2." WHERE p_etat =\"valid\" ";
                $sql2=$sql2." AND p_login IN ($list_users_du_grand_resp) ";
                $sql2=$sql2." ORDER BY p_num";

                $ReqLog2 = \includes\SQL::query($sql2) ;

                $count2=$ReqLog2->num_rows;
                if($count2!=0)
                {
                    // AFFICHAGE TABLEAU DES DEMANDES EN COURS POUR DEUXIEME VALIDATION

                    echo "<h3>". _('resp_traite_demandes_titre_tableau_2') ."</h3>\n" ;

                    echo "<table class=\"table table-hover table-responsive table-condensed table-striped\">\n" ;
                    echo "<thead>\n" ;
                    echo "<tr>\n" ;
                    echo "<th><b>". _('divers_nom_maj_1') ."</b><br>". _('divers_prenom_maj_1') ."</th>\n" ;
                    echo "<th>". _('divers_quotite_maj_1') ."</th>" ;
                    echo "<th>". _('divers_debut_maj_1') ."</th>\n" ;
                    echo "<th>". _('divers_fin_maj_1') ."</th>\n" ;
                    echo "<th>". _('divers_comment_maj_1') ."</th>\n" ;
                    echo "<th>". _('resp_traite_demandes_nb_jours') ."</th>";
                    foreach($tab_type_conges as $id_conges => $libelle)
                        echo "<th>". _('divers_solde_maj_1') ."<br>$libelle</th>" ;
                    echo "<th>". _('divers_type_maj_1') ."</th>\n" ;
                    echo "<th>". _('divers_accepter_maj_1') ."</th>\n" ;
                    echo "<th>". _('divers_refuser_maj_1') ."</th>\n" ;
                    echo "<th>". _('resp_traite_demandes_attente') ."</th>\n" ;
                    echo "<th>". _('resp_traite_demandes_motif_refus') ."</th>\n" ;
                    if($_SESSION['config']['affiche_date_traitement'])
                    {
                        echo "<th>". _('divers_date_traitement') ."</th>\n" ;
                    }
                    echo "</tr>\n";
                    echo "</thead>\n" ;
                    echo "<tbody>\n" ;

                    $i = true;
                    $tab_bt_radio=array();
                    while ($resultat2 = $ReqLog2->fetch_array()) 
                    {
                        /** sur la ligne ,   **/
                        /** le 1er bouton radio est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--OK"> */
                        /**  et le 2ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--not_OK"> */
                        /**  et le 3ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--RIEN"> */

                        $sql_p_date_deb = $resultat2["p_date_deb"];
                        $sql_p_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]);
                        $sql_p_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                        if($sql_p_demi_jour_deb=="am") $demi_j_deb="matin";  else $demi_j_deb="après-midi";
                        $sql_p_date_fin = $resultat2["p_date_fin"];
                        $sql_p_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]);
                        $sql_p_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                        if($sql_p_demi_jour_fin=="am") $demi_j_fin="matin";  else $demi_j_fin="après-midi";
                        $sql_p_commentaire = $resultat2["p_commentaire"];
                        $sql_p_num = $resultat2["p_num"];
                        $sql_p_login = $resultat2["p_login"];
                        $sql_p_nb_jours = affiche_decimal($resultat2["p_nb_jours"]);
                        $sql_p_type = $resultat2["p_type"];
                        $sql_p_date_demande = $resultat2["p_date_demande"];
                        $sql_p_date_traitement = $resultat2["p_date_traitement"];

                        // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                        $chaine_bouton_radio = "$sql_p_login--$sql_p_nb_jours--$sql_p_type--$sql_p_date_deb--$sql_p_demi_jour_deb--$sql_p_date_fin--$sql_p_demi_jour_fin";

                        $boutonradio1="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--OK\">";
                        $boutonradio2="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--not_OK\">";
                        $boutonradio3="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--RIEN\" checked>";

                        $text_refus="<input class=\"form-control\" type=\"text\" name=\"tab_text_refus[$sql_p_num]\" size=\"20\" max=\"100\">";

                        echo '<tr class="'.($i?'i':'p').'">';
                        echo "<td><strong>".$tab_all_users_du_grand_resp[$sql_p_login]['nom']."</strong><br>".$tab_all_users_du_grand_resp[$sql_p_login]['prenom']."</td><td>".$tab_all_users_du_grand_resp[$sql_p_login]['quotite']."%</td>";
                        echo "<td>$sql_p_date_deb_fr <span class=\"demi\">$demi_j_deb<span></td><td>$sql_p_date_fin_fr <span class=\"demi\">$demi_j_fin</span></td><td>$sql_p_commentaire</td><td><b>$sql_p_nb_jours</b></td>";
                        $tab_conges=$tab_all_users_du_grand_resp[$sql_p_login]['conges']; 
                        foreach($tab_type_conges as $id_conges => $libelle)
                        {
                            echo "<td>".$tab_conges[$libelle]['solde']."</td>";
                        }
                        echo "<td>".$tab_type_all_abs[$sql_p_type]['libelle']."</td>";
                        echo "<td>$boutonradio1</td><td>$boutonradio2</td><td>$boutonradio3</td><td>$text_refus</td>\n";
                        if($_SESSION['config']['affiche_date_traitement'])
                        {
                            echo "<td class=\"histo-left\">". _('divers_demande') ." : $sql_p_date_demande<br>". _('divers_traitement') ." : $sql_p_date_traitement</td>\n" ;
                        }

                        echo "</tr>\n" ;
                        $i = !$i;
                    } //while
                    echo "</tbody>\n" ;
                    echo "</table>\n\n" ;
                } //if($count2!=0)
            } //if( count($tab_all_users_du_grand_resp)!=0 )
        } //if($_SESSION['config']['double_validation_conges'])

        echo "<br>\n";

        if(($count1==0) && ($count2==0))
            echo "<strong>". _('resp_traite_demandes_aucune_demande') ."</strong>\n";		
        else {
            echo "<hr/>\n";
            echo "<input class=\"btn btn-success\" type=\"submit\" value=\"". _('form_submit') ."\">\n" ;
        }

        echo " </form> \n" ;
    }
    
    /**
     * Encapsule le comportement du module de traitement des congés des utilisateurs sous la responsabilité du responsable
     *
     * @return void
     * @access public
     * @static
     */
    public static function traitementDemandesModule($tab_type_cong, $DEBUG = false)
    {
        //var pour resp_traite_demande_all.php
        $tab_bt_radio   = getpost_variable('tab_bt_radio');
        $tab_text_refus = getpost_variable('tab_text_refus');


        // titre
        echo "<h1>". _('resp_traite_demandes_titre') ."</h1>";


        // si le tableau des bouton radio des demandes est vide , on affiche les demandes en cours
        if($tab_bt_radio=="")
            \responsable\Fonctions::affiche_all_demandes_en_cours($tab_type_cong,  $DEBUG);
        else
            \responsable\Fonctions::traite_all_demande_en_cours( $tab_bt_radio, $tab_text_refus, $DEBUG);
    }
}
