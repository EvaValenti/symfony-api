<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\VideoGame;
use App\Enum\Mime;
use App\Repository\VideoGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class VideoGameController extends AbstractController
{
    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Response(
        response: 200,
        description: 'Accueil du contrôleur des jeux vidéo.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Welcome to your new controller!'),
                new OA\Property(property: 'path', type: 'string', example: 'src/Controller/VideoGameController.php')
            ]
        )
    )]
    #[Route('/videogame', name: 'app_video_game')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoGameController.php',
        ]);
    }

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Response(response: 200, description: 'Retourne la liste paginée des jeux vidéo.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: VideoGame::class, groups: ['getVideoGames']))))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Numéro de la page.', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Nombre d\'éléments par page.', schema: new OA\Schema(type: 'integer', default: 5))]
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

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant du jeu vidéo.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Retourne un jeu vidéo selon son identifiant.', content: new OA\JsonContent(ref: new Model(type: VideoGame::class, groups: ['getVideoGame'])))]
    #[OA\Response(response: 404, description: 'Jeu vidéo introuvable.')]
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

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\RequestBody(description: 'Données de création d\'un jeu vidéo.', required: true, content: new OA\JsonContent(ref: new Model(type: VideoGame::class, groups: ['getVideoGame'])))]
    #[OA\Response(response: 201, description: 'Jeu vidéo créé avec succès.', content: new OA\JsonContent(ref: new Model(type: VideoGame::class, groups: ['getVideoGame'])))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/videogame/create', name: 'createVideoGame', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createVideoGame(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
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

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant du jeu vidéo à modifier.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(description: 'Nouvelles données du jeu vidéo.', required: true, content: new OA\JsonContent(ref: new Model(type: VideoGame::class, groups: ['getVideoGame'])))]
    #[OA\Response(response: 200, description: 'Jeu vidéo mis à jour avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/videogame/update/{id}', name: 'updateVideoGame', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateVideoGame(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, VideoGame $currentVideoGame, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
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

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant du jeu vidéo à supprimer.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Jeu vidéo supprimé avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: 'Jeu vidéo introuvable.')]
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

    #[OA\Tag(name: 'jeux vidéo')]
    #[OA\Response(response: 200, description: 'Retourne les prochains jeux à sortir.', content: new OA\JsonContent(type: 'string'))]
    public function upcomingGames(EntityManagerInterface $entityManager): Response
    {
        $upcomingGames = $entityManager->getRepository(VideoGame::class)->findUpcomingGames();

        return $this->render('templates/mail.html.twig', [
            'games' => $upcomingGames,
        ]);
    }
}
