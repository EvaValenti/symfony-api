<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
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

final class CategoryController extends AbstractController
{
    #[OA\Tag(name: 'catégories')]
    #[OA\Response(
        response: 200,
        description: 'Accueil du contrôleur des catégories.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Welcome to your new controller!'),
                new OA\Property(property: 'path', type: 'string', example: 'src/Controller/CategoryController.php')
            ]
        )
    )]
    #[Route('/category', name: 'app_category')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/CategoryController.php',
        ]);
    }

    #[OA\Tag(name: 'catégories')]
    #[OA\Response(
        response: 200,
        description: 'Retourne la liste paginée des catégories.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Category::class, groups: ['getCategories']))
        )
    )]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Numéro de la page.', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Nombre d\'éléments par page.', schema: new OA\Schema(type: 'integer', default: 5))]
    #[Route('/api/v1/categories', name: 'categories', methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getCategories(CategoryRepository $categoryRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $cacheIdentifier = "getAllProjects".$page."-".$limit;

        $categoriesList = $cachePool->get(
            $cacheIdentifier,
            function (ItemInterface $item) use ($categoryRepository, $page, $limit){
                $item->tag("categoryCache");
                return $categoryRepository->findAllWithPagination($page, $limit);
            }
        );

        $jsonCategories = $serializer->serialize($categoriesList, 'json', ['groups' => 'getCategories']);

        return new JsonResponse($jsonCategories, Response::HTTP_OK, [], true);
    }

    #[OA\Tag(name: 'catégories')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de la catégorie.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Retourne une catégorie selon son identifiant.', content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['getCategory'])))]
    #[OA\Response(response: 404, description: 'Catégorie introuvable.')]
    #[Route('/api/v1/category/{id}', name: 'category', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    public function getCategory(int $id, CategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);

        if($category){
            return $this->json($category, 'json', ['groups' => 'getCategory']);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[OA\Tag(name: 'catégories')]
    #[OA\RequestBody(description: 'Données de création d\'une catégorie.', required: true, content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['getCategory'])))]
    #[OA\Response(response: 201, description: 'Catégorie créée avec succès.', content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['getCategory'])))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/category/create', name: 'createCategory', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createCategory(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $category = $serializer->deserialize($request->getContent(), Category::class, 'json');
        $errors = $validator->validate($category);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["categoryCache"]);
            $em->persist($category);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'category',
            ['id' => $category->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            $category, Response::HTTP_CREATED,
            ['Location' => $location], ['groups' => 'getCategory']
        );
    }

    #[OA\Tag(name: 'catégories')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de la catégorie à modifier.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(description: 'Nouvelles données de la catégorie.', required: true, content: new OA\JsonContent(ref: new Model(type: Category::class, groups: ['getCategory'])))]
    #[OA\Response(response: 200, description: 'Catégorie mise à jour avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
    #[Route('/api/v1/category/update/{id}', name: 'updateCategory', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateCategory(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, Category $currentCategory, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $updatedCategory = $serializer->deserialize($request->getContent(), Category::class, 'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCategory]);
        $errors = $validator->validate($updatedCategory);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["categoryCache"]);
            $em->persist($updatedCategory);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'category',
            ['id' => $updatedCategory->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK,
            ['Location' => $location]
        );
    }

    #[OA\Tag(name: 'catégories')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Identifiant de la catégorie à supprimer.', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Catégorie supprimée avec succès.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'status', type: 'string', example: 'success')]))]
    #[OA\Response(response: 404, description: 'Catégorie introuvable.')]
    #[Route('/api/v1/category/delete/{id}', name: 'deleteCategory', methods: ['DELETE'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à supprimer cet élément.')]
    public function deleteCategory(Category $category, EntityManagerInterface $em,  TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["categoryCache"]);
        $em->remove($category);
        $em->flush();

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK
        );
    }
}
