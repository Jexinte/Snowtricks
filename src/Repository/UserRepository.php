<?php

namespace App\Repository;

use App\Controller\DTO\UserDTO;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Persistence\ManagerRegistry;
use App\Enumeration\UserStatus;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


    /**
     * @param UserDTO $userDto
     * @return string[]|null
     * @throws \Exception
     */
    public function createUser(UserDTO $userDto):?array
{
$user = new User();
$entityManager = $this->getEntityManager();
$usernameNotAvailable = $this->findOneBy(["name" => $userDto->getName()]);
$emailNotAvailable = $this->findOneBy(["email" => $userDto->getEmail()]);
switch (true){
    case $usernameNotAvailable:
        return [
            'username_unavailable' => 'Le nom utilisateur '.$usernameNotAvailable->getName().' n\'est pas disponible',
        ];

    case $emailNotAvailable:
        return [
            'email_unavailable' => "L'adresse email ". $emailNotAvailable->getEmail() ." n'est pas disponible"
        ];
    default:
        $fileExt = explode('.',$userDto->getFile()->getClientOriginalName());
        $filename = str_replace("/", "", base64_encode(random_bytes(9))).'.'.$fileExt[1];
        $imgPath = "http://localhost:8000/Snowtricks/public/assets/img/$filename";
        $user->setName($userDto->getName());
        $user->setProfileImage($imgPath);
        $user->setEmail($userDto->getEmail());
        $user->setPassword($userDto->getPassword());
        $user->setStatus(UserStatus::ACCOUNT_NOT_ACTIVATE);
        $tmp = $userDto->getFile()->getPathname();
        $dir = "../public/assets/img";
        move_uploaded_file($tmp,"$dir/$filename");
        $entityManager->persist($user);
        $entityManager->flush();

}

return null;
}

public function updateUserStatus(Request $request): bool
{
    $usernameInSession = $request->getSession()->get('username');
    $entityManager = $this->getEntityManager();
    $dataToUpdate = $entityManager->getRepository(User::class)->findBy(["name" => $usernameInSession]);
    foreach ($dataToUpdate as $record){
        $record->setStatus(UserStatus::ACCOUNT_ACTIVATE);
    }
    $entityManager->flush();

    return true;
}

    /**
     * @param UserDTO $userDTO
     * @param Request $request
     * @return array<string>|bool
     */
    public function login(UserDTO $userDTO,Request $request):array|bool
{
    $usernameFromForm = $userDTO->getName();
    $passwordFromForm = $userDTO->getPassword();

    $userInDb = current($this->findBy(["name" => $usernameFromForm]));
    switch (true)
    {
        case !$userInDb:
            return ["username_failed" => "Oops ! Identifiant ou mot de passe incorrect. Veuillez vérifier vos informations de connexion !"];
        case $userInDb->getName() == $usernameFromForm &&
            password_verify(
                $passwordFromForm,
                $userInDb->getPassword()
            ):
                if($userInDb->getStatus() == UserStatus::ACCOUNT_ACTIVATE){
                    return [
                        "connected" => UserStatus::CONNECTED,
                        "user_id" => $userInDb->getId(),
                    ];
                }
                return ["not_activate" => UserStatus::ACCOUNT_NOT_ACTIVATE];

        default:
            return["password_failed" => "Oops ! Il semblerait que le mot de passe saisi est incorrect !"];

    }



}

public function checkUser(UserDTO $userDto):?User
{
    $userMatch = $this->findOneBy(["name" => $userDto->getName()]);
    if($userMatch) {
        return $userMatch;
    }
    return null;
}

public function checkPasswordReset(UserDTO $userDto):bool|array|null
{
    $userDataFromDb = $this->checkUser($userDto);

    $oldPasswordFromForm = $userDto->getOldPassword();
    $newPasswordFromForm = $userDto->getPassword();
    if(!is_null($userDataFromDb)){
        if(password_verify($oldPasswordFromForm,$userDataFromDb->getPassword()))
        {
            $entityManager = $this->getEntityManager();
            $dataToUpdate = $entityManager->getRepository(User::class)->findBy(["name" => $userDataFromDb->getName()]);
            foreach ($dataToUpdate as $record){
                $record->setPassword(password_hash($newPasswordFromForm,PASSWORD_DEFAULT));
            }
            $entityManager->flush();
            return null;

        }
        return ["password" => "Oops ! Il semble que le mot de passe saisi est incorrect !"];
    }

    return ["username" => "Oops ! Identifiant ou mot de passe incorrect. Veuillez vérifier vos informations de connexion !"];
}
}
