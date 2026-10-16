<?php

namespace App\Controller;

use App\Entity\Editor;
use App\Repository\EditorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

final class EditorController extends AbstractController
{
    #[OA\Tag(name: 'éditeurs')]
    #[OA\Response(
        response: 200,
        description: 'Accueil du contrôleur des éditeurs.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Welcome to your new controller!'),
                new OA\Property(property: 'path', type: 'string', example: 'src/Controller/EditorController.php')
            ]
        )
    )]
    #[Route('/editor', name: 'app_editor')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/EditorController.php',
        ]);
    }

    #[OA\Tag(name: 'éditeurs')]
    #[OA\Response(response: 200, description: 'Retourne la liste paginée des éditeurs.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Editor::class, groups: ['getEditors']))))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Numéro de la page.', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Nombre d\'éléments par page.', schema: new OA\Schema(type: 'integer', default: 5))]
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

    #[OA\Tag(name: 'éditeurs')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de l\'éditeur.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Retourne un éditeur selon son identifiant.', content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['getEditor'])))]
    #[OA\Response(response: 404, description: 'Éditeur introuvable.')]
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

    #[OA\Tag(name: 'éditeurs')]
    #[OA\RequestBody(description: 'Données de création d\'un éditeur.', required: true, content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['getEditor'])))]
    #[OA\Response(response: 201, description: 'Éditeur créé avec succès.', content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['getEditor'])))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/editor/create', name: 'createEditor', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createEditor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
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

    #[OA\Tag(name: 'éditeurs')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de l\'éditeur à modifier.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(description: 'Nouvelles données de l\'éditeur.', required: true, content: new OA\JsonContent(ref: new Model(type: Editor::class, groups: ['getEditor'])))]
    #[OA\Response(response: 200, description: 'Éditeur mis à jour avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/editor/update/{id}', name: 'updateEditor', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateEditor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, Editor $currentEditor, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
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

    #[OA\Tag(name: 'éditeurs')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de l\'éditeur à supprimer.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Éditeur supprimé avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: 'Éditeur introuvable.')]
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
