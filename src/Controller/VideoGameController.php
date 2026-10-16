<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\VideoGameRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use App\Entity\VideoGame;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\File;
use App\Enum\Mime;

final class VideoGameController extends AbstractController
{
    #[Route('/videogame', name: 'app_video_game')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoGameController.php',
        ]);
    }

    #[Route('/api/v1/videogames', name: 'videogames', methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getVideoGames(VideoGameRepository $videoGameRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $cacheIdentifier = "getAllVideoGames".$page."-".$limit;

        $videoGamesList = $cachePool->get(
            $cacheIdentifier,
            function (ItemInterface $item) use ($videoGameRepository, $page, $limit){
                $item->tag("videoGameCache");
                return $videoGameRepository->findAllWithPagination($page, $limit);
            }
        );

        $jsonVideoGames = $serializer->serialize($videoGamesList, 'json', ['groups' => 'getVideoGames']);

        return new JsonResponse($jsonVideoGames, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/videogame/{id}', name: 'videoGame', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    public function getVideoGame(int $id, VideoGameRepository $videoGameRepository): JsonResponse
    {
        $videoGame = $videoGameRepository->find($id);

        if($videoGame){
            return $this->json($videoGame, 'json', ['groups' => 'getVideoGame']);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/v1/videogame/create', name: 'createVideoGame', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createVideoGame(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
    {
        $videoGame = $serializer->deserialize($request->getContent(), VideoGame::class, 'json');
        $errors = $validator->validate($videoGame);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $coverImage = $request->files->get('coverImage');
            if ($coverImage) {
            $newFilename = uniqid().'.'.$coverImage->guessExtension();
            try {
                $coverImage->move(
                    $this->getParameter('cover_directory'), $newFilename
                );
                $file = new File();
                $file->setOriginalName($coverImage->getClientOriginalName());
                $file->setStoredName($newFilename);
                $file->setSize($coverImage->getSize());

                $mimeType = Mime::tryFrom($coverImage->getMimeType());
                if ($mimeType === null) {
                    return new JsonResponse(['error' => 'Type d’image non pris en charge.'], 400);
                }
                $file->setMimeType($mimeType);

                $em->persist($file);
                $videoGame->setCoverImage($file);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Impossible d\'enregistrer l\'image.'], 500);
            }
        }
            $cachePool->invalidateTags(["videoGameCache"]);
            $em->persist($videoGame);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'videoGame',
            ['id' => $videoGame->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            $videoGame, Response::HTTP_CREATED,
            ['Location' => $location], ['groups' => 'getVideoGame']
        );
    }

    #[Route('/api/v1/videogame/update/{id}', name: 'updateVideoGame', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateVideoGame(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, VideoGame $currentVideoGame, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
    {
        $updatedVideoGame = $serializer->deserialize($request->getContent(), VideoGame::class, 'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentVideoGame]);
        $errors = $validator->validate($updatedVideoGame);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["videoGameCache"]);
            $em->persist($updatedVideoGame);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'videoGame',
            ['id' => $updatedVideoGame->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK,
            ['Location' => $location]
        );
    }

    #[Route('/api/v1/videogame/delete/{id}', name: 'deleteVideoGame', methods: ['DELETE'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à supprimer cet élément.')]
    public function deleteVideoGame(VideoGame $videoGame, EntityManagerInterface $em,  TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["videoGameCache"]);
        $em->remove($videoGame);
        $em->flush();

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK
        );
    }

    public function upcomingGames(EntityManagerInterface $entityManager): Response
    {
        $upcomingGames = $entityManager->getRepository(VideoGame::class)->findUpcomingGames();

        return $this->render('templates/mail.html.twig', [
            'games' => $upcomingGames,
        ]);
    }
}
