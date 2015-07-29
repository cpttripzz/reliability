<?php
/**
 * Created by PhpStorm.
 * User: zach
 * Date: 7/29/15
 * Time: 4:22 PM
 */

namespace NatInt\Services;


class AuthService {

    public function doUserLogin($email, $password)
    {
        $user = Users::findFirst(array(
            "(email = :email: OR username = :email:) AND password = :password: AND active = 'Y'",
            'bind' => array('email' => $email, 'password' => sha1($password))
        ));
    }
}