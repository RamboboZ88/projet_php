<?php

namespace mywishlist\controleurs;


use Illuminate\Support\Facades\Auth;
use mywishlist\exceptions\AuthException;
use mywishlist\exceptions\InscriptionException;
use mywishlist\models\Role;
use mywishlist\models\User;
use mywishlist\vue\VueAccount;
use mywishlist\vue\VueParticipant;
use Slim\Container;

class ControleurUser
{

    private $container;

    public function __construct(Container $c){
        $this->container = $c;
    }

    public function listerUsers($rq, $rs, $args ){
        $users = User::all();
        foreach ($users as $user){
            $rs->getBody()->write($user . $user->role);
        }
        return $rs;
    }

    public function listerRoles($rq, $rs, $args ){
        $roles = Role::all();
        foreach ($roles as $role){
            $rs->getBody()->write($role);
            $users = $role->users;
            foreach ($users as $user){
                $rs->getBody()->write($user);
            }
        }
        return $rs;
    }

    /**
     * Créé un formulaire d'inscription pour un utilisateur
     */
    public function formulaireInscription($rq, $rs, $args ){
        $vue = new VueAccount($this->container);
        $rs->write($vue->render(1));
        return $rs;
    }

    /**
     * Inscrit un utilisateur
     */
    public function inscription($rq, $rs, $args ){
        $data = $rq->getParsedBody();
        $username = $data['username'];
        $password = $data['password'];
        $email = $data['email'];
        try{
            Authentification::createUser($username,$password,'Createur', $email);
            $rs->write("Utilisateur ". $username." inscrit");
        }
        catch(InscriptionException $e1){
            $rs->write($e1->getMessage());
        }
        return $rs;
    }

    public function formulaireConnexion($rq, $rs, $args ){
        $vue = new VueAccount($this->container);
        $rs->write($vue->render(2));
        return $rs;
    }


    public function connexion($rq, $rs, $args){
        $vue = new VueAccount($this->container);
        $data = $rq->getParsedBody();
        $username = $data['username'];
        $password = $data['password'];
        try{
            Authentification::authenticate($username,$password);
            $rs->write($vue->render(3));
        }
        catch(AuthException $e1){
            $rs->write($e1->getMessage());
        }
        return $rs;
    }

    public function deconnexion($rq, $rs, $args){
        Authentification::deconnexion();
        $rs->write("Vous êtes déconnecté");
        $url = $this->container->router->pathFor('accueil');
        $rs = $rs->withStatus(302)->withHeader('Location', $url);
        return $rs;
    }



    /**
     * Crée un utilisateur
     * @param $username String nom d'utilisateur
     * @param $password String mot de passe
     * @param $email String email de l'utilisateur
     * @throws InscriptionException
     */
    public static function createUser ($username, $password, $userRole,$email){
        // Teste taille du password.
        if(strlen($password) < 12){
            throw new InscriptionException("Le password doit avoir au moins 12 caractères");
        }
        // Teste au moins 1 majuscule.
        $passwordTestNbMajs = preg_replace('#[a-z]*#', '', $password);
        $nbmaj = strlen($passwordTestNbMajs);
        if ($nbmaj == 0)
        {
            throw new InscriptionException("Le password doit avoir au moins une majuscule");
        }

        // si ok : hacher $password
        password_hash($password, PASSWORD_DEFAULT, ['cost'=> 10]);


        // créer et enregistrer l'utilisateur
        $user = new User();
        $user->inscrireUser($username, $password, $userRole, $email);
        $userid = $user->userid;
        self::loadProfile($userid);

    }

    /**
     * Voir les infos de son compte
     */
    public function voirCompte($rq, $rs, $args){
        try{
            Authentification::checkAccessRights(Authentification::$CREATOR_RIGHTS);
            $userid = $_SESSION['profile']['userid'];
            $user = User::firstWhere('userid',$userid);
            $vue = new VueAccount($this->container,$user);
            $rs->write($vue->render(4));
        }
        catch (AuthException $e1){
            $v = new VueAccount($this->container);
            $rs->write($v->render(5));
        }
        return $rs;
    }

    /**
     * Modifier les infos de son compte
     */
    public function formModifCompte($rq, $rs, $args){
        try{
            Authentification::checkAccessRights(Authentification::$CREATOR_RIGHTS);
            $userid = $_SESSION['profile']['userid'];
            $user = User::firstWhere('userid',$userid);
            $v = new VueAccount($this->container,$user);
            $rs->write($v->render(6));
        }
        catch (AuthException $e1){
            $v = new VueAccount($this->container);
            $rs->write($v->render(5));
        }
        return $rs;
    }

    public function modifCompte($rq, $rs, $args){
        try {
            Authentification::checkAccessRights(Authentification::$CREATOR_RIGHTS);
            $data = $rq->getParsedBody();
            //TODO sécuriser injection
            $newMail = $data['email'];
            $newPassword = $data['password'];
            $user = User::firstWhere('userid', $_SESSION['profile']['userid']);
            $user->password = $newPassword;
            $user->email = $newMail;
            $user->save();
            if (strlen($newPassword) != 0) {
                Authentification::deconnexion();
                $url = $this->container->router->pathFor('formConnexion');
            } else {
                $this->container->router->pathFor('formConnexion');
                $url = $this->container->router->pathFor('voirProfil');
            }
            $rs = $rs->withStatus(302)->withHeader('Location', $url);
        }
        catch (AuthException $e1){
            $v = new VueAccount($this->container);
            $rs->write($v->render(5));
        }
        return $rs;
    }

}