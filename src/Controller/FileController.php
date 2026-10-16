<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class FileController extends AbstractController
{
    #[OA\Tag(name: 'fichiers')]
    #[OA\Response(
        response: 200,
        description: 'Accueil du contrôleur des fichiers.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Welcome to your new controller!'),
                new OA\Property(property: 'path', type: 'string', example: 'src/Controller/FileController.php')
            ]
        )
    )]
    #[Route('/file', name: 'app_file')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/FileController.php',
        ]);
    }

    #[OA\Tag(name: 'fichiers')]
    #[OA\Response(response: 200, description: 'Retourne la liste paginée des fichiers.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: File::class))))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Numéro de la page.', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Nombre d\'éléments par page.', schema: new OA\Schema(type: 'integer', default: 5))]
    #[Route('/api/v1/files', name: 'files', methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getFiles(FileRepository $fileRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $cacheIdentifier = "getAllFiles".$page."-".$limit;

        $filesList = $cachePool->get(
            $cacheIdentifier,
            function (ItemInterface $item) use ($fileRepository, $page, $limit){
                $item->tag("fileCache");
                return $fileRepository->findAllWithPagination($page, $limit);
            }
        );

        $jsonFiles = $serializer->serialize($filesList, 'json', ['groups' => 'getFiles']);

        return new JsonResponse($jsonFiles, Response::HTTP_OK, [], true);
    }

    #[OA\Tag(name: 'fichiers')]
    #[OA\RequestBody(description: 'Données de création d\'un fichier.', required: true, content: new OA\JsonContent(ref: new Model(type: File::class)))]
    #[OA\Response(response: 201, description: 'Fichier créé avec succès.', content: new OA\JsonContent(ref: new Model(type: File::class)))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/file/create', name: 'createFile', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createFile(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $file = $serializer->deserialize($request->getContent(), File::class, 'json');
        $errors = $validator->validate($file);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["fileCache"]);
            $em->persist($file);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'file',
            ['id' => $file->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            $file, Response::HTTP_CREATED,
            ['Location' => $location], ['groups' => 'getFile']
        );
    }

    #[OA\Tag(name: 'fichiers')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant du fichier à modifier.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(description: 'Nouvelles données du fichier.', required: true, content: new OA\JsonContent(ref: new Model(type: File::class)))]
    #[OA\Response(response: 200, description: 'Fichier mis à jour avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/file/update/{id}', name: 'updateFile', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateFile(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, File $currentFile, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $updatedFile = $serializer->deserialize($request->getContent(), File::class, 'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentFile]);
        $errors = $validator->validate($updatedFile);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["videoFile"]);
            $em->persist($updatedFile);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'file',
            ['id' => $updatedFile->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK,
            ['Location' => $location]
        );
    }

    #[OA\Tag(name: 'fichiers')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant du fichier à supprimer.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Fichier supprimé avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: 'Fichier introuvable.')]
    #[Route('/api/v1/file/delete/{id}', name: 'deleteFile', methods: ['DELETE'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à supprimer cet élément.')]
    public function deleteFile(File $file, EntityManagerInterface $em,  TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["fileCache"]);
        $em->remove($file);
        $em->flush();

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK
        );
    }
}
