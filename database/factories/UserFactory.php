<?php
namespace Database\Factories; use Illuminate\Database\Eloquent\Factories\Factory; class UserFactory extends Factory {public function definition():array{return ['username'=>$this->faker->unique()->userName(),'name'=>$this->faker->name(),'email'=>$this->faker->unique()->safeEmail(),'password'=>'password'];}}
