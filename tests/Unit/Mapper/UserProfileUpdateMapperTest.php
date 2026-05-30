<?php

namespace App\Tests\Unit\Mapper;

use App\Dto\User\UserProfileUpdateDto;
use App\Entity\User;
use App\Mapper\UserProfileUpdateMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProfileUpdateMapperTest extends TestCase
{
    public function testUpdatePhoneOnly(): void
    {
        $user = (new User())
            ->setPhone('06 11 22 33 44')
            ->setPassword('existing-hash');

        $dto = new UserProfileUpdateDto();
        $dto->phone = '06 55 66 77 88';

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->never())->method('hashPassword');

        $mapper = new UserProfileUpdateMapper($passwordHasher);
        $mapper->updateUserFromDto($user, $dto);

        $this->assertSame('06 55 66 77 88', $user->getPhone());
        $this->assertSame('existing-hash', $user->getPassword());
    }

    public function testUpdatePasswordOnly(): void
    {
        $user = (new User())
            ->setPhone('06 11 22 33 44')
            ->setPassword('existing-hash');

        $dto = new UserProfileUpdateDto();
        $dto->plainPassword = 'NewPassword1!';

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'NewPassword1!')
            ->willReturn('new-hash');

        $mapper = new UserProfileUpdateMapper($passwordHasher);
        $mapper->updateUserFromDto($user, $dto);

        $this->assertSame('06 11 22 33 44', $user->getPhone());
        $this->assertSame('new-hash', $user->getPassword());
    }
}
