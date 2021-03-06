<?php

namespace App\Http\Controllers;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Http\Request;
use Config;
use Session;
use App\Utilisateur;
use App\Droit;
use App\M_Projet;
use App\CorrespPackage;
use App\Package;

class Settings extends Controller
{
    public function getForm()
    {
        //Déclaration des constantes
        $constants = Config::get('constants');
        //Déclaration de l'Erreur
        $erreur = 0;
        //Récupération de l'id utilisateur
        $id_utilisateur = Session::get('id_utilisateur');
        //Si l'utilisateur est connecté
        if(isset($id_utilisateur))
        {
            //Si un projet est spécifié
            if(isset($_GET["id_projet"]) && !empty($_GET["id_projet"]))
            {
                //Récupération des infos de l'utilisateur
                $utilisateur = Utilisateur::where('id', $id_utilisateur)->first();
                $id_projet = $_GET["id_projet"]; //L'id du projet spécifié en URL
                //Récupération des infos du projet
                $projet = M_Projet::where('id', $id_projet)->first();
                //Si le projet existe
                if(isset($projet))
                {
                    //On récupère les droits de l'utilisateur sur le projet
                    $droit = Droit::where('id_projet', $projet->id)->where('id_utilisateur', $id_utilisateur)->first();
                    //Si l'utilisateur est admin
                    if($droit->role == $constants["ROLE_ADMIN"])
                    {
                        //Nous allons alors construire la liste des ayants droits
                        //On récupère d'abord cette liste dans la bdd
                        $droits = Droit::where('id_projet', $projet->id)->get();
                        $data = []; //Déclaration du tableau de retour
                        //Puis pour chaque ligne de la bdd
                        for($i = 0; $i < sizeof($droits); $i++)
                        {
                            $droit = $droits[$i]; //Droits sur le projet de l'itération
                            //On récupère les infos de l'utilisateur de l'itération
                            $developpeur = Utilisateur::where('id', $droit["id_utilisateur"])->first();
                            $iteration = array(); //Déclaration du tableau de l'itération
                            //Puis affectation des valeurs au tableau temporaire
                            $iteration["id"] = $developpeur->id; //L'id
                            $iteration["nom"] = $developpeur->nom; //Le nom
                            $iteration["prenom"] = $developpeur->prenom; //Le prénom
                            $iteration["email"] = $developpeur->email; //L'email
                            $iteration["role"] = $droit["role"]; //Le rôle
                            //Ajout du tableau temporaire au tableau de retour
                            $data[$i] = $iteration;
                        }

                        //on récupure dans la bdd la liste des packages correspondant a ce projet
                        $packagenbr= CorrespPackage::where('id_projet',$projet->id)->get();
                        // déclaration du tableau de retour
                        $infopackage= [];
                        // pour chacun ligne en bdd
                        for($i =0; $i<sizeof($packagenbr);$i++){

                          $packageite=$packagenbr[$i]; // package de l'$iteration
                          // on Récupère les information du package en bdd
                          $packagecourant = Package::where('id',$packageite['id_package'])->first();

                          $iteration=array();

                          $iteration["nom_package"]= $packagecourant->nom; // on ajoute le nom du package dans du tableau de l'iteration

                          $infopackage[$i]=$iteration; // ajout dans le tableau de retour
                        }
                        //On redirige vers la vue
                        return view('settings', compact('utilisateur', 'projet', 'data', 'constants','infopackage'));
                    }
                    else
                    {
                        //L'utilisateur n'a pas les droits sur le projet
                        $erreur = $constants["ACCESS_DENIED"];
                    }
                }
                else
                {
                    //Le projet n'existe pas
                    $erreur = $constants["INVALID_PROJECT"];
                }
            }
            else
            {
                //Le projet n'est pas spécifié
                $erreur = $constants["INVALID_PROJECT"];
            }
        }
        else
        {
            //Sinon, redirection vers la page de connexion
            return redirect('login');
        }
        //En cas d'erreur, on redirige vers l'accueil
        return redirect('/?erreur='.$erreur);
    }

    public function postForm(Request $request)
    {
        //Déclaration des constantes
        $constants = Config::get('constants');
        $unwanted_array = $constants["UNWANTED_ARRAY"];
        //Récupération des valeurs des formulaires
        $id_projet = $request->input('id_projet'); //l'id projet
        $id_utilisateur = $request->input('id_utilisateur'); //l'id utilisateur
        $action = $request->input('action'); //l'action à effectuer
        $new_project_name = $request->input('projectName');//le nouveau nom du projet
        $new_role= $request->input('role_utilisateur'); // on récupère le nouveau role dans l'url

        if(isset($new_role)){

            // récupération des info sur les droits dans la bd
            $info_util= Droit::where('id_utilisateur', $id_utilisateur)->where('id_projet', $id_projet)->first();
            //Affectation du nouveau role
            $info_util->role= $new_role;
            //Enregistrement en bdd
            $info_util->save();

            return redirect('settings?id_projet='.$id_projet);
        }

        //Si validation du nouveau nom du projet
        if(isset($new_project_name))
        {
            $id_projet = $_GET["id_projet"]; //L'id du projet spécifié en URL
            //Récupération des infos du projet
            $projet = M_Projet::where('id', $id_projet)->first();
            //Affectation du nouveau nom de projet
            $projet->nom = $new_project_name;
            //Enregistrement en base de données
            $projet->save();

            //On redirige vers la vue settings
            return redirect('settings?id_projet='.$id_projet."&erreur=".$constants["VALID"]);


        }

        //Si les champs sont remplis
        if(!empty($id_projet) && !empty($action) && !empty($id_utilisateur))
        {
	        $projet = M_Projet::where('id', $id_projet)->first();

            //Et si l'action correspond à un effacement
            if($action == "delete")
            {
                //On efface en bdd
                $utilisateur = Utilisateur::where('id', $id_utilisateur)->first();
                $process = new Process(['../docker/deluser.sh', $projet->port, preg_replace("/[^a-zA-Z0-9]+/", "", strtolower(strtr($utilisateur->prenom, $unwanted_array )).strtolower(strtr($utilisateur->nom, $unwanted_array )))]);
                $return_code = $process->run();
                if($return_code == 0)
                {
                    Droit::where('id_utilisateur', $id_utilisateur)->where('id_projet', $id_projet)->delete();
                    //Puis on redirige vers la vue settings
                    return redirect('settings?id_projet='.$id_projet);
                }
                else
                {
                    //Redirection vers l'accueil avec l'erreur, en cas d'échec
                    return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["DOCKER_ERROR"]);
                }
            }
            //si l'action correspond à un effacement de projet
            if($action == "delete-projet")
            {
                $process = new Process(['../docker/rm.sh', $projet->id]);
                $return_code = $process->run();
                if($return_code == 0)
                {
                    //efface en bd
                    Droit::where('id_projet', $id_projet)->delete();
                    M_Projet::where('id', $id_projet)->delete();
                }
                //Puis on redirige vers la vue acceuil
                return redirect('/');
            }
            if($action == "delete-package")
            {
              	$pack = Package::where('nom', $request->input('nom_package'))->first();
              	$process = new Process(['../docker/delpackage.sh', $projet->port, $pack->nom]);
              	$return_code = $process->run();
	      	if($return_code == 0)
	      	{
			CorrespPackage::where('id_package', $pack->id)->where('id_projet', $_GET["id_projet"])->delete();
              	  	return redirect('settings?id_projet='.$_GET["id_projet"]);
	      	}
		else
		{
			return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["DOCKER_ERROR"]);
		}
            }
        }

        //Récupération des info du package donné
        $pack = Package::where('id', $request->input('name_package'))->first();
        //Récupération des infos de l'utilisateur donné
        $utilisateur = Utilisateur::where('id', $request->input('email_utilisateur'))->first();

        //Si l'utilisateur demandé existe dans le cas d'un ajout d'utilisateur
        if(isset($utilisateur))
        {
            //Et si le projet est spécifié
            if(!empty($_GET["id_projet"]))
            {
                //On vérifie qu'il ne soit pas déjà autorisé
                $droit = Droit::where('id_utilisateur', $utilisateur->id)->where('id_projet', $id_projet)->first();
                if(!isset($droit))
                {
                    $projet = M_Projet::where('id', $id_projet)->first();
                    $process = new Process(['../docker/adduser.sh', $projet->port, preg_replace("/[^a-zA-Z0-9]+/", "", strtolower(strtr($utilisateur->prenom, $unwanted_array )).strtolower(strtr($utilisateur->nom, $unwanted_array ))), strtolower($utilisateur->unix_password)]);
                    $return_code = $process->run();
                    if($return_code == 0)
                    {
                        //On ajoute les droits pour l'utilisateur donné
                        $droit = new Droit; //Instanciation d'un objet de type Droit
                        $droit->id_utilisateur = $utilisateur->id; //Affectation de l'id utilisateur
                        $droit->id_projet = $_GET["id_projet"]; //Affectation de l'id projet

                        //Option radio passé dans le formulaire
                        $role = $request->input('optionsRadios');

                        if($role == "option1")
                        {
                            //Premier : role admin
                            $role = $constants["ROLE_ADMIN"];
                        }
                        else
                        {
                            //Deuxième : role dev
                            $role = $constants["ROLE_DEV"];
                        }

                        $droit->role = $role; //Affectation du role
                        $droit->save(); //Enregistrement en bdd
                        //Redirection vers la page settings, en cas de succes
                        return redirect('settings?id_projet='.$_GET["id_projet"]);
                    }
                    else
                    {
                        //Redirection vers l'accueil avec l'erreur, en cas d'échec
                        return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["DOCKER_ERROR"]);
                    }
                }
                else
                {
                    //Redirection vers l'accueil avec l'erreur, en cas d'échec
                    return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["ALREADY_AUTHORIZED"]);
                }
            }
            else
            {
                //Redirection vers l'accueil avec l'erreur, en cas d'échec
                return redirect('/?erreur='.$constants["INVALID_PROJECT"]);
            }
        }
        /////////
        //Si le nom du package existe dans le cas d'un ajout de package
        else if(isset($pack))
        {
            //Et si le projet est spécifié
            if(!empty($_GET["id_projet"]))
            {

                  //On vérifie que le package n'est pas déjà ajouté
                  $verif = CorrespPackage::where('id_package', $pack->id)->where('id_projet', $_GET["id_projet"])->first();

                  if(!isset($verif))
                  {
		    $projet = M_Projet::where('id', $_GET["id_projet"])->first();
		    $process = new Process(['../docker/addpackage.sh', $projet->port, $pack->nom]);
                    $return_code = $process->run();
		    if($return_code == 0)
		    {
                    	$corresp = new CorrespPackage; //Instanciation d'un objet de type CorrespPackage
                    	$corresp->id_package = $pack->id; //Affectation de l'id du package
                    	$corresp->id_projet = $_GET["id_projet"]; //Affectation de l'id projet
                    	$corresp->save();
			return redirect('settings?id_projet='.$_GET["id_projet"]);
		    }
		    else
		    {
                    	return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["DOCKER_ERROR"]);
		    }
                }
                else
                {
                    //Redirection vers l'accueil avec l'erreur, en cas d'échec
                    return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["ALREADY_ADD"]);
                }

            }
            else
            {
                //Redirection vers l'accueil avec l'erreur, en cas d'échec
                return redirect('/?erreur='.$constants["INVALID_PROJECT"]);
            }
          }

       else
       {
            //Redirection vers l'accueil avec l'erreur, en cas d'échec
            return redirect('settings?id_projet='.$_GET["id_projet"].'&erreur='.$constants["CHAMP_VIDE"]);
        }


}
}
