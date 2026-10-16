<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\EditorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\Editor;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EditorController extends AbstractController
{
    #[Route('/editor', name: 'app_editor')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/EditorController.php',
        ]);
    }

    #[Route('/api/v1/editors', name: 'editors', methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getEditors(EditorRepository $editorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $cacheIdentifier = "getAllEditors".$page."-".$limit;

        $editorsList = $cachePool->get(
            $cacheIdentifier,
            function (ItemInterface $item) use ($editorRepository, $page, $limit){
                $item->tag("editorCache");
                return $editorRepository->findAllWithPagination($page, $limit);
            }
        );

        $jsonEditors = $serializer->serialize($editorsList, 'json', ['groups' => 'getEditors']);

        return new JsonResponse($jsonEditors, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/editor/{id}', name: 'editor', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    public function getEditor(int $id, EditorRepository $editorRepository): JsonResponse
    {
        $editor = $editorRepository->find($id);

        if($editor){
            return $this->json($editor, 'json', ['groups' => 'getEditor']);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/v1/editor/create', name: 'createEditor', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createEditor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
    {
        $editor = $serializer->deserialize($request->getContent(), Editor::class, 'json');
        $errors = $validator->validate($editor);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["editorCache"]);
            $em->persist($editor);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'editor',
            ['id' => $editor->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            $editor, Response::HTTP_CREATED,
            ['Location' => $location], ['groups' => 'getEditor']
        );
    }

    #[Route('/api/v1/editor/update/{id}', name: 'updateEditor', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateEditor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, Editor $currentEditor, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
    {
        $updatedEditor = $serializer->deserialize($request->getContent(), Editor::class, 'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentEditor]);
        $errors = $validator->validate($updatedEditor);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["editorCache"]);
            $em->persist($updatedEditor);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'editor',
            ['id' => $updatedEditor->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK,
            ['Location' => $location]
        );
    }

    #[Route('/api/v1/editor/delete/{id}', name: 'deleteEditor', methods: ['DELETE'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à supprimer cet élément.')]
    public function deleteEditor(Editor $editor, EntityManagerInterface $em,  TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["editorCache"]);
        $em->remove($editor);
        $em->flush();

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK
        );
    }
}
