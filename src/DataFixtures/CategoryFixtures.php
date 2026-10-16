<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Faker\Factory;

class CategoryFixtures extends Fixture implements FixtureGroupInterface
{
    public const CATEGORY_REF = 'category_';

    public static function getGroups(): array
    {
        return ['categories'];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i=0; $i < 30; $i++) {
            $category = new Category();
            $category->setName($faker->sentence(1));

            $manager->persist($category);
            $this->addReference(self::CATEGORY_REF . $i, $category);
        }

        $manager->flush();
    }

}
