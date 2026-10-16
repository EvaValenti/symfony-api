<?php

namespace App\Controller;

use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;

final class FileController extends AbstractController
{
    #[Route('/file', name: 'app_file')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/FileController.php',
        ]);
    }

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

    #[Route('/api/v1/file/create', name: 'createFile', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createFile(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
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

    #[Route('/api/v1/file/update/{id}', name: 'updateFile', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateFile(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, File $currentFile, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
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
