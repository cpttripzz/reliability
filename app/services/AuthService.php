<?php
/**
 * Created by PhpStorm.
 * User: zach
 * Date: 7/29/15
 * Time: 4:22 PM
 */

namespace NatInt\Services;
use NatInt\Models\Users;
use Phalcon\Security;

class AuthService {

    protected $security;
    public function __construct(Security $security)
    {
        $this->security = $security;
    }
    public function getUserByCredentials($request)
    {
        $username = $request->getPost('username');
        $password = $request->getPost('password');
        $user = Users::findFirst(array('username' => $username));
        if($user){
            if (!$this->security->checkHash($password, $user->password)) {
                return false;
            }
        }
        return $user;
    }
}