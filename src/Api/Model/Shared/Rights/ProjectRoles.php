<?php

namespace Api\Model\Shared\Rights;

class ProjectRoles extends RolesBase
{
    const MANAGER = 'project_manager';
    const CONTRIBUTOR = 'contributor';
    const TECH_SUPPORT = 'project_manager';

    const NONE = 'none';

    public static function getRolesList() {
        $roles = array(
            self::MANAGER => "Manager",
            self::CONTRIBUTOR => "Contributor"
        );
        return $roles;
    }

    /**
     * @param $role string the user's role
     * @return bool the status of manager rights for a user
     */
    public static function isManager(string $role) {
        return ($role == self::MANAGER || $role == self::TECH_SUPPORT);
    }
}
