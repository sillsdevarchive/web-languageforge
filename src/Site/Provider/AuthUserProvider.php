<?php

namespace Site\Provider;

use Api\Library\Shared\Website;
use Api\Model\Shared\Rights\SiteRoles;
use Api\Model\Shared\Rights\SystemRoles;
use Api\Model\Shared\UserModel;
use Api\Model\Shared\UserModelWithPassword;
use Site\Model\UserWithId;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
Use Symfony\Component\Security\Core\User\UserInterface;

class AuthUserProvider implements UserProviderInterface
{
    public function __construct(Website $website = null) {
        $this->website = $website;
    }

    /** @var Website */
    private $website;

    /**
     * @param string $usernameOrEmail
     * @return UserWithId
     */
    public function loadUserByUsername($usernameOrEmail) {

        $user = new UserModelWithPassword();
        $user->readByUsernameOrEmail($usernameOrEmail);

        if ($user->id->asString() == '') {
            throw new UsernameNotFoundException();
        }
        if (!$user->active) {
            // TODO: Get this error msg to propogate to Auth::setupAuthView
            throw new UsernameNotFoundException(sprintf('Username "%s" access denied on "%s".', $usernameOrEmail, $this->website->domain));
        }

        if (!$user->hasRoleOnSite($this->website) and $user->role != SystemRoles::SYSTEM_ADMIN) {
            $shouldThrowException = true;

            // special case: if known user on languageforge.org logs in on scriptureforge.org and vice versa, we automatically add them to the site.
            // This is because scriptureforge and languageforge are sister sites where cross-login is expected and allowed.
            $sisterSiteMap = array(
                'scriptureforge.org' => 'languageforge.org',
                'scriptureforge.localhost' => 'languageforge.localhost',
                'dev.scriptureforge.org' => 'dev.languageforge.org',
                'qa.scriptureforge.org' => 'qa.languageforge.org'
            );
            $sisterSiteMap = array_merge($sisterSiteMap, array_flip($sisterSiteMap));
            if (array_key_exists($this->website->domain, $sisterSiteMap)) {
                $otherWebsite = Website::get($sisterSiteMap[$this->website->domain]);
                if ($user->hasRoleOnSite($otherWebsite)) {
                    $shouldThrowException = false;
                    $user->siteRole[$this->website->domain] = $this->website->userDefaultSiteRole;
                    $user->write();
                }
            }

            if ($shouldThrowException) {
                throw new AccessDeniedException(sprintf('Username "%s" not available on "%s". Use "Create an Account".', $usernameOrEmail, $this->website->domain));
            }
        }

        /*
        $identityCheck = UserCommands::checkIdentity($usernameOrEmail, '', $this->website);
        if (! $identityCheck->usernameExists) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $usernameOrEmail));
        }

        $user->readByUserName($usernameOrEmail);

        if (! $identityCheck->usernameExistsOnThisSite and $user->role != SystemRoles::SYSTEM_ADMIN) {
            throw new AccessDeniedException(sprintf('Username "%s" not available on "%s". Use "Create an Account".', $usernameOrEmail, $this->website->domain));
        }
        */

        $roles = AuthUserProvider::getSiteRoles($user, $this->website);

        return new UserWithId($user->username, $user->password, $user->id->asString(), $roles);
    }

    /**
     * @param UserInterface $user
     * @return UserInterface $user
     */
    public function refreshUser(UserInterface $user) {
        if (! $user instanceof UserWithId) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class) {
        return $class === 'Site\Model\UserWithId';
    }

    public static function getSiteRoles(UserModel $user, Website $website)
    {
        $roles = array('ROLE_'.$user->role);
        if ($user->siteRole and
            $user->siteRole->offsetExists($website->domain) and
            $user->siteRole[$website->domain] !== SiteRoles::NONE) {
            $roles[] = 'ROLE_SITE_'.$user->siteRole[$website->domain];
        }
        return $roles;
    }
}
