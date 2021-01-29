<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


use App\Models\User;

class UserController extends Controller
{
    public function whoAmI(){
        $array = ['error' => ''];

        $user = auth()->user();

        $array['user'][] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'cpf' => $user['cpf'],
        ];

        return $array;
    }

    public function userInfo($id){
        $array = ['error' => ''];

        $userInfo = User::find($id);

        if ($userInfo) {

            $array['user'][] = [
                'id' => $userInfo['id'],
                'name' => $userInfo['name'],
                'email' => $userInfo['email'],
                'cpf' => $userInfo['cpf'],
            ];

        } else {
            $array['error'] = 'Usuario inexistente!';
            return $array;
        }

        return $array;
    }

    public function editUser($id, Request $request){
        $array = ['error' => ''];

        $user = User::find($id);

        if ($user) {

            $validator = Validator::make($request->all(), [
                'email' => 'email|unique:users,email',
                'cpf' => 'digits:11|unique:users,cpf',
                'password_confirm' => 'same:password'               
            ]);

            if (!$validator->fails()){

                if ($request->input('name')) {
                    $name = $request->input('name');
                    $user->name = $name;
                } 

                if ($request->input('email')) {
                    $email = $request->input('email');
                    $user->email = $email;
                } 

                if ($request->input('cpf')) {
                    $cpf = $request->input('cpf');
                    $user->cpf = $cpf;
                } 

                if ($request->input('password')) {
                    $password = $request->input('password');
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $user->password = $hash;
                } 

                $user->save();

                $array['user'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'cpf' => $user['cpf'],
                    'email' => $user['email']
                ];             

            } else {
                $array['error'] = $validator->errors()->first();
                return $array;
            }        

        } else {
            $array['error'] = "Usuario nao existe";
            return $array;
        }

        return $array;
    }
}
