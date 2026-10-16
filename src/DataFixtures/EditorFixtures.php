<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use App\Entity\Editor;
use Faker\Factory;

class EditorFixtures extends Fixture implements FixtureGroupInterface
{
    public const EDITOR_REF = 'editor_';

    public static function getGroups(): array {
        return ['editors'];
    }

    public function load(ObjectManager $manager): void {
        $faker = Factory::create('fr_FR');

        for($i=0; $i < 30; $i++){
            $editor = new Editor();
            $editor->setName($faker->sentence(1));
            $editor->setCountry($faker->sentence(1));

            $manager->persist($editor);

            $this->addReference(self::EDITOR_REF.$i, $editor);
        }

        $manager->flush();
    }
}
