<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Category;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class CategoryController extends AbstractController
{
    #[Route('/category', name: 'app_category')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/CategoryController.php',
        ]);
    }

    #[Route('/api/v1/categories', name: 'categories', methods: ['GET'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getCategories(CategoryRepository $categoryRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // request->get() n'existe plus en Symfony 8.1
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

    #[Route('/api/v1/category/create', name: 'createCategory', methods: ['POST'])]
    #[IsGranted('USER', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createCategory(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
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

    #[Route('/api/v1/category/update/{id}', name: 'updateCategory', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateCategory(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, Category $currentCategory, TagAwareCacheInterface $cachePool, ValidatorInterface $validator): JsonResponse
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
