<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class UserController extends AbstractController
{
    #[Route('/user', name: 'app_user')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ]);
    }

    #[Route('/api/v1/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser ces éléments.')]
    public function getUsers(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 5);

        $cacheIdentifier = "getAllUsers".$page."-".$limit;

        $usersList = $cachePool->get(
            $cacheIdentifier,
            function (ItemInterface $item) use ($userRepository, $page, $limit){
                $item->tag("userCache");
                return $userRepository->findAllWithPagination($page, $limit);
            }
        );

        $jsonUsers = $serializer->serialize($usersList, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    #[Route('/api/v1/user/{id}', name: 'user', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à visualiser cet élément.')]
    public function getSingleUser(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if($user){
            return $this->json($user, 'json', ['groups' => 'getUser']);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/v1/user/create', name: 'createUser', methods: ['POST'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à créer cet élément.')]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');
        $errors = $validator->validate($user);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["userCache"]);
            $em->persist($user);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'user',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            $user, Response::HTTP_CREATED,
            ['Location' => $location], ['groups' => 'getUser']
        );
    }

    #[Route('/api/v1/user/update/{id}', name: 'updateUser', methods: ['PUT'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à modifier cet élément.')]
    public function updateUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, User $currentUser, TagAwareCacheInterface $cachePool, ValidatorInterface $validator)
    {
        $updatedUser = $serializer->deserialize($request->getContent(), User::class, 'json',
        [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]);
        $errors = $validator->validate($updatedUser);
        if($errors->count() > 0){
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        } else {
            $cachePool->invalidateTags(["userCache"]);
            $em->persist($updatedUser);
            $em->flush();
        }

        $location = $urlGenerator->generate(
            'user',
            ['id' => $updatedUser->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK,
            ['Location' => $location]
        );
    }

    #[Route('/api/v1/user/delete/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ADMIN', message: 'Vous n\'êtes pas autorisé à supprimer cet élément.')]
    public function deleteUser(User $user, EntityManagerInterface $em,  TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["userCache"]);
        $em->remove($user);
        $em->flush();

        return $this->json(
            ['status' => 'success'], Response::HTTP_OK
        );
    }

    // Manual login route that returns a JWT token.
    // Note: lexik/jwt-authentication-bundle already provides a token endpoint if configured (check_path in security.yaml),
    // but this explicit controller method lets you control the response format and flow.
    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function login(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtManager->create($user);

        return $this->json(['token' => $token], Response::HTTP_OK);
    }
}
