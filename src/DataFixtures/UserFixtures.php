<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Faker\Factory;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
        {
            return ['users'];
        }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for($i=0; $i < 10; $i++){
            $user = new User();
            $user->setEmail($faker->email());
            $user->setPassword(password_hash("Password123*", PASSWORD_ARGON2I));
            $random_role = rand(1, 2);
            if($random_role === 1){
                $user->setRoles(["USER"]);
            } else {
                $user->setRoles(["ADMIN"]);
            }
            $user->setSubscriptionToNewsletter($faker->boolean());

            $manager->persist($user);
        }

        $manager->flush();
    }
}
