<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class UserController extends AbstractController
{
    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'Accueil du contrôleur utilisateur.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Welcome to your new controller!'),
                new OA\Property(property: 'path', type: 'string', example: 'src/Controller/UserController.php')
            ]
        )
    )]
    #[Route('/user', name: 'app_user')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ]);
    }

    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Response(
        response: 200,
        description: 'Retourne la liste paginée des utilisateurs.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: User::class, groups: ['getUsers']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Numéro de la page.',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre d\'éléments par page.',
        schema: new OA\Schema(type: 'integer', default: 5)
    )]
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

    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant de l\'utilisateur.',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Retourne un utilisateur selon son identifiant.',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['getUser']))
    )]
    #[OA\Response(response: 404, description: 'Utilisateur introuvable.')]
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

    #[OA\Tag(name: 'utilisateurs')]
    #[OA\RequestBody(
        description: 'Données nécessaires à la création d\'un utilisateur.',
        required: true,
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['getUser']))
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur créé avec succès.',
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['getUser']))
    )]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
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

    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant de l\'utilisateur à modifier.',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        description: 'Nouvelles données de l\'utilisateur.',
        required: true,
        content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['getUser']))
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur mis à jour avec succès.',
        content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'status', type: 'string', example: 'success')
        ])
    )]
    #[OA\Response(response: 400, description: 'Données invalides ou validation échouée.')]
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

    #[OA\Tag(name: 'utilisateurs')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant de l\'utilisateur à supprimer.',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur supprimé avec succès.',
        content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'status', type: 'string', example: 'success')
        ])
    )]
    #[OA\Response(response: 404, description: 'Utilisateur introuvable.')]
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

    #[OA\Tag(name: 'authentification')]
    #[OA\RequestBody(
        description: 'Identifiants utilisés pour obtenir un token JWT.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'plainpassword')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Retourne un token JWT valide.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJ...')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Email ou mot de passe manquant.')]
    #[OA\Response(response: 401, description: 'Identifiants invalides.')]
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
