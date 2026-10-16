<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Editor;
use App\Entity\VideoGame;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Faker\Factory;

class VideoGameFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const VIDEO_GAME_REF = 'video_game_';

    public static function getGroups(): array
        {
            return ['categories'];
        }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            EditorFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for($i=0; $i < 50; $i++){
            $videoGame = new VideoGame();
            $videoGame->setTitle($faker->sentence(3));
            $videoGame->setReleaseDate($faker->dateTime());
            $videoGame->setDescription($faker->paragraph());

            $editorNumber = random_int(0, 29);
            $editorRef = EditorFixtures::EDITOR_REF.$editorNumber;

            $categoryNumber = random_int(0, 29);
            $category = $this->getReference(
                CategoryFixtures::CATEGORY_REF . $categoryNumber,
                Category::class
            );

            $category->addVideoGame($videoGame);

            $videoGame->setEditorId($this->getReference($editorRef, Editor::class));

            $manager->persist($videoGame);

            $this->addReference(self::VIDEO_GAME_REF.$i, $videoGame);
        }

        $manager->flush();
    }
}
