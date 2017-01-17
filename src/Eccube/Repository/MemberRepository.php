<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Repository;

use Doctrine\ORM\EntityRepository;
use Eccube\Common\Constant;
use Eccube\Entity\Member;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * MemberRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MemberRepository extends EntityRepository implements UserProviderInterface
{
    /**
     * @var EncoderFactoryInterface
     */
    private $encoder_factory;

    /**
     * @param EncoderFactoryInterface $encoder_factory
     */
    public function setEncoderFactorty(EncoderFactoryInterface $encoder_factory)
    {
        $this->encoder_factory = $encoder_factory;
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        $Work = $this
            ->getEntityManager()
            ->getRepository('Eccube\Entity\Master\Work')
            ->find(\Eccube\Entity\Master\Work::WORK_ACTIVE_ID);

        $query = $this->createQueryBuilder('m')
            ->where('m.login_id = :login_id')
            ->andWhere('m.Work = :Work')
            ->setParameters(array(
                    'login_id' => $username,
                    'Work' => $Work,
            ))
            ->setMaxResults(1)
            ->getQuery();
        $Member = $query->getOneOrNullResult();
        if (!$Member) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $Member;
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof Member) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === 'Eccube\Entity\Member';
    }

    /**
     * @param  \Eccube\Entity\Member $Member
     *
     * @return void
     */
    public function up(\Eccube\Entity\Member $Member)
    {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();
        try {
            $rank = $Member->getRank();

            $Member2 = $this->findOneBy(array('rank' => $rank + 1));
            if (!$Member2) {
                throw new \Exception();
            }
            $Member2->setRank($rank);
            $em->persist($Member2);

            // Member更新
            $Member->setRank($rank + 1);

            $em->persist($Member);
            $em->flush();

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

            return false;
        }

        return true;
    }

    /**
     * @param  \Eccube\Entity\Member $Member
     * @return bool
     */
    public function down(\Eccube\Entity\Member $Member)
    {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();
        try {
            $rank = $Member->getRank();

            //
            $Member2 = $this->findOneBy(array('rank' => $rank - 1));
            if (!$Member2) {
                throw new \Exception();
            }
            $Member2->setRank($rank);
            $em->persist($Member2);

            // Member更新
            $Member->setRank($rank - 1);

            $em->persist($Member);
            $em->flush();

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

            return false;
        }

        return true;
    }

    /**
     * @param  \Eccube\Entity\Member $Member
     * @return bool
     */
    public function save(\Eccube\Entity\Member $Member)
    {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();
        try {
            if (!$Member->getId()) {
                $rank = $this->createQueryBuilder('m')
                    ->select('MAX(m.rank)')
                    ->getQuery()
                    ->getSingleScalarResult();
                if (!$rank) {
                    $rank = 0;
                }
                $Member
                    ->setRank($rank + 1)
                    ->setDelFlg(Constant::DISABLED);
            }

            $em->persist($Member);
            $em->flush();

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

            return false;
        }

        return true;
    }

    /**
     * @param  \Eccube\Entity\Member $Member
     * @return bool
     */
    public function delete(\Eccube\Entity\Member $Member)
    {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();
        try {
            $rank = $Member->getRank();
            $em->createQueryBuilder()
                ->update('Eccube\Entity\Member', 'm')
                ->set('m.rank', 'm.rank - 1')
                ->where('m.rank > :rank')->setParameter('rank', $rank)
                ->getQuery()
                ->execute();

            $Member
                ->setDelFlg(Constant::ENABLED)
                ->setRank(0);

            $em->persist($Member);
            $em->flush();

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

            return false;
        }

        return true;
    }

    /**
     * saltを生成する
     *
     * @param $byte
     * @return string
     */
    public function createSalt($byte)
    {
        return bin2hex(openssl_random_pseudo_bytes($byte));
    }

    /**
     * 入力されたパスワードをSaltと暗号化する
     *
     * @param $app
     * @param  \Eccube\Entity\Member $Member
     * @return mixed
     */
    public function encryptPassword(\Eccube\Entity\Member $Member)
    {
        $encoder = $this->encoder_factory->getEncoder($Member);

        return $encoder->encodePassword($Member->getPassword(), $Member->getSalt());
    }
}
