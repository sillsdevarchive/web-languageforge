<?php

namespace Api\Service;

use Api\Library\Scriptureforge\Sfchecks\ParatextExport;
use Api\Library\Scriptureforge\Sfchecks\SfchecksReports;
use Api\Library\Shared\Palaso\Exception\UserNotAuthenticatedException;
use Api\Library\Shared\Palaso\Exception\UserUnauthorizedException;
use Api\Library\Shared\SilexSessionHelper;
use Api\Library\Shared\Website;
use Api\Model\Languageforge\Lexicon\Command\LexCommentCommands;
use Api\Model\Languageforge\Lexicon\Command\LexEntryCommands;
use Api\Model\Languageforge\Lexicon\Command\LexOptionListCommands;
use Api\Model\Languageforge\Lexicon\Command\LexProjectCommands;
use Api\Model\Languageforge\Lexicon\Command\LexUploadCommands;
use Api\Model\Languageforge\Lexicon\Command\SendReceiveCommands;
use Api\Model\Languageforge\Lexicon\Dto\LexBaseViewDto;
use Api\Model\Languageforge\Lexicon\Dto\LexDbeDto;
use Api\Model\Languageforge\Lexicon\Dto\LexProjectDto;
use Api\Model\Languageforge\Semdomtrans\Command\SemDomTransItemCommands;
use Api\Model\Languageforge\Semdomtrans\Command\SemDomTransProjectCommands;
use Api\Model\Languageforge\Semdomtrans\Command\SemDomTransWorkingSetCommands;
use Api\Model\Languageforge\Semdomtrans\Dto\SemDomTransAppManagementDto;
use Api\Model\Languageforge\Semdomtrans\Dto\SemDomTransEditDto;
use Api\Model\Scriptureforge\Sfchecks\Command\SfchecksProjectCommands;
use Api\Model\Scriptureforge\Sfchecks\Command\SfchecksUploadCommands;
use Api\Model\Scriptureforge\Sfchecks\Command\QuestionCommands;
use Api\Model\Scriptureforge\Sfchecks\Command\QuestionTemplateCommands;
use Api\Model\Scriptureforge\Sfchecks\Command\TextCommands;
use Api\Model\Scriptureforge\Sfchecks\Dto\ProjectPageDto;
use Api\Model\Scriptureforge\Sfchecks\Dto\ProjectSettingsDto;
use Api\Model\Scriptureforge\Sfchecks\Dto\QuestionCommentDto;
use Api\Model\Scriptureforge\Sfchecks\Dto\QuestionListDto;
use Api\Model\Scriptureforge\Sfchecks\Dto\TextSettingsDto;
use Api\Model\Scriptureforge\Sfchecks\Dto\UsxHelper;
use Api\Model\Shared\Command\MessageCommands;
use Api\Model\Shared\Command\ProjectCommands;
use Api\Model\Shared\Command\SessionCommands;
use Api\Model\Shared\Command\UserCommands;
use Api\Model\Shared\Communicate\EmailSettings;
use Api\Model\Shared\Communicate\SmsSettings;
use Api\Model\Shared\Dto\ActivityListDto;
use Api\Model\Shared\Dto\ProjectListDto;
use Api\Model\Shared\Dto\ProjectManagementDto;
use Api\Model\Shared\Dto\RightsHelper;
use Api\Model\Shared\Dto\UserProfileDto;
use Api\Model\Shared\Mapper\JsonEncoder;
use Api\Model\Shared\ProjectModel;
use Api\Model\Shared\Translate\Command\TranslateDocumentSetCommands;
use Api\Model\Shared\Translate\Command\TranslateMetricCommands;
use Api\Model\Shared\Translate\Command\TranslateProjectCommands;
use Api\Model\Shared\Translate\Dto\TranslateDocumentSetDto;
use Api\Model\Shared\Translate\Dto\TranslateProjectDto;
use Api\Model\Shared\UserListModel;
use Api\Model\Shared\UserModel;
use Silex\Application;
use Site\Controller\Auth;

require_once APPPATH . 'vendor/autoload.php';

class Sf
{
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->website = Website::get();
        $this->userId = SilexSessionHelper::getUserId($app);
        $this->projectId = SilexSessionHelper::getProjectId($app, $this->website);

        // "Kick" session every time we use an API call, so it won't time out
        $this->update_last_activity();

        // TODO put in the LanguageForge style error handler for logging / jsonrpc return formatting etc. CP 2013-07
        ini_set('display_errors', 0);
    }

    /** @var Application */
    private $app;

    /** @var Website */
    private $website;

    /** @var string */
    private $userId;

    /** @var string */
    private $projectId;

    // ---------------------------------------------------------------
    // IMPORTANT NOTE TO THE DEVELOPERS
    // ---------------------------------------------------------------
    // When adding a new api method, also add your method name and appropriate RightsHelper statement as required by
    // the method's context (project context or site context) to the RightsHelper::userCanAccessMethod() method
    // FYI userCanAccessMethod() is a whitelist. Anything not explicitly listed is denied access
    //
    // If an api method is ever renamed, remember to update the name in this method as well
    // ---------------------------------------------------------------

    /*
     * --------------------------------------------------------------- BELLOWS ---------------------------------------------------------------
     */

    // ---------------------------------------------------------------
    // USER API
    // ---------------------------------------------------------------

    /**
     * Read a user from the given $id
     *
     * @param string $id
     * @return array
     */
    public function user_read($id)
    {
        return UserCommands::readUser($id);
    }

    /**
     * Read the user profile from $id
     *
     * @return array
     */
    public function user_readProfile()
    {
        return UserProfileDto::encode($this->userId, $this->website);
    }

    /**
     * Ban a User from the given $id
     *
     * @params string $id
     * @return string Id of banned user
     */
    public function user_ban($id)
    {
        return UserCommands::banUser($id);
    }

    /**
     * Create/Update a User
     *
     * @param array $params (encoded UserModel)
     * @return string Id of written object
     */
    public function user_update($params)
    {
        return UserCommands::updateUser($params, $this->website);
    }

    /**
     * Update a User Profile
     * Changing username will notify client to signout
     *
     * @param array $params (encoded UserModel)
     * @return  bool|string False if update failed; $userId on update; 'login' on
     *  username change to notify client to signout
     */
    public function user_updateProfile($params)
    {
        $result = UserCommands::updateUserProfile($params, $this->userId, $this->website);
        if ($result == 'login') {
            // Username changed
            $this->app['session']->getFlashBag()->add('infoMessage', 'Username changed. Please login.');
        }
        return $result;
    }

    /**
     * Delete users
     *
     * @param array<string> $userIds
     * @return int Count of deleted users
     */
    public function user_delete($userIds)
    {
        return UserCommands::deleteUsers($userIds);
    }

    /**
     * @param string $username
     * @return array CreateSimpleDto
     */
    public function user_createSimple($username)
    {
        return UserCommands::createSimple($username, $this->projectId, $this->userId, $this->website);
    }

    // TODO Pretty sure this is going to want some paging params
    /**
     * @return UserListModel
     */
    public function user_list()
    {
        return UserCommands::listUsers();
    }

    public function user_typeahead($term, $projectIdToExclude = '')
    {
        return UserCommands::userTypeaheadList($term, $projectIdToExclude, $this->website);
    }

    public function user_typeaheadExclusive($term, $projectIdToExclude = '')
    {
        $projectIdToExclude = empty($projectIdToExclude) ? $this->projectId : $projectIdToExclude;
        return UserCommands::userTypeaheadList($term, $projectIdToExclude, $this->website);
    }

    public function change_password($userId, $newPassword)
    {
        return UserCommands::changePassword($userId, $newPassword, $this->userId);
    }

    public function reset_password($resetPasswordKey, $newPassword)
    {
        return Auth::resetPassword($this->app, $resetPasswordKey, $newPassword);
    }

    public function check_unique_identity($userId, $updatedUsername, $updatedEmail)
    {
        if ($userId) {
            $user = new UserModel($userId);
        } else {
            $user = new UserModel();
        }
        return UserCommands::checkUniqueIdentity($user, $updatedUsername, $updatedEmail);
    }

    /**
     * Register a new user with password and optionally add them to a project if allowed by permissions
     *
     * @param array $params
     * @return string Id of written object
     */
    public function user_register($params)
    {
        $result = UserCommands::register($params, $this->website, $this->app['session']->get('captcha_info'));
        if ($result == 'login') {
            Auth::login($this->app, UserCommands::sanitizeInput($params['email']), $params['password']);
        }
        return $result;
    }

    public function user_register_oauth($params)
    {
        $result = UserCommands::registerOAuthUser($params, $this->website);
        if ($result == 'login') {
            Auth::loginWithoutPassword($this->app, UserCommands::sanitizeInput($params['username']));
        }
        return $result;
    }

    public function user_calculate_username($usernameBase)
    {
        return UserCommands::calculateUniqueUsernameFromString($usernameBase);
    }

    public function user_create($params)
    {
        return UserCommands::createUser($params, $this->website);
    }

    public function get_captcha_data() {
        return UserCommands::getCaptchaData($this->app['session']);
    }

    public function user_sendInvite($toEmail)
    {
        return UserCommands::sendInvite($this->projectId, $this->userId, $this->website, $toEmail);
    }

    // ---------------------------------------------------------------
    // GENERAL PROJECT API
    // ---------------------------------------------------------------

    public function project_sendJoinRequest($projectID)
    {
        return UserCommands::sendJoinRequest($projectID, $this->userId, $this->website);
    }


    /**
     * @param string $projectName
     * @param string $projectCode
     * @param string $appName
     * @param array $srProject send receive project data
     * @return string | boolean - $projectId on success, false if project code is not unique
     */
    public function project_create($projectName, $projectCode, $appName, $srProject = null)
    {
        return ProjectCommands::createProject($projectName, $projectCode, $appName, $this->userId, $this->website, $srProject);
    }

    /**
     * Creates project and switches the session to the new project
     *
     * @param string $projectName
     * @param string $projectCode
     * @param string $appName
     * @param array $srProject
     * @return string|bool $projectId on success, false if project code is not unique
     */
    public function project_create_switchSession($projectName, $projectCode, $appName, $srProject)
    {
        $projectId = $this->project_create($projectName, $projectCode, $appName, $srProject);
        $this->app['session']->set('projectId', $projectId);
        return $projectId;
    }

    /**
     * Join user to project and switches the session to the new project
     *
     * @param string $srIdentifier
     * @param string $role
     * @return string|bool $projectId on success, false if project code doesn't exist
     * @throws \Exception
     */
    public function project_join_switchSession($srIdentifier, $role)
    {
        $projectId = SendReceiveCommands::getProjectIdFromSendReceive($srIdentifier);
        if (!$projectId) return false;

        ProjectCommands::updateUserRole($projectId, $this->userId, $role);
        $this->app['session']->set('projectId', $projectId);
        return $projectId;
    }

    /**
     * Clear out the session projectId and archive project
     *
     * @return string
     */

    public function project_archive()
    {
        $this->app['session']->set('projectId', "");
        return ProjectCommands::archiveProject($this->projectId, $this->userId);
    }

    public function project_archivedList()
    {
        return ProjectListDto::encode($this->userId, $this->website, true);
    }

    /**
     * Publish selected list of archived projects
     *
     * @param array<string> $projectIds
     * @return int Count of published projects
     */
    public function project_publish($projectIds)
    {
        return ProjectCommands::publishProjects($projectIds);
    }

    // TODO Pretty sure this is going to want some paging params
    public function project_list()
    {
        return ProjectCommands::listProjects();
    }

    public function project_list_dto()
    {
        return ProjectListDto::encode($this->userId, $this->website);
    }

    public function project_joinProject($projectId, $role)
    {
        return ProjectCommands::updateUserRole($projectId, $this->userId, $role);
    }

    public function project_usersDto()
    {
        return ProjectCommands::usersDto($this->projectId);
    }

    public function project_getJoinRequests()
    {
        return ProjectCommands::getJoinRequests($this->projectId);
    }

    /**
     * Clear out the session projectId and permanently delete selected list of projects.
     *
     * @param array<string> $projectIds Default to current projectId
     * @return int Total number of projects removed.
     */
    public function project_delete($projectIds)
    {
        if (empty($projectIds)) {
            $projectIds = [$this->projectId];
        }
        $this->app['session']->set('projectId', "");
        return ProjectCommands::deleteProjects($projectIds, $this->userId);
    }

    // ---------------------------------------------------------------
    // SESSION API
    // ---------------------------------------------------------------
    public function session_getSessionData()
    {
        return SessionCommands::getSessionData($this->projectId, $this->userId, $this->website);
    }

    public function projectcode_exists($code)
    {
        return ProjectCommands::projectCodeExists($code);
    }

    // ---------------------------------------------------------------
    // Activity Log
    // ---------------------------------------------------------------
    public function valid_activity_types_dto()
    {
        return ActivityListDto::getActivityTypes($this->website);
    }

    public function activity_list_dto($filterParams = [])
    {
        return ActivityListDto::getActivityForUser($this->website->domain, $this->userId, $filterParams);
    }

    public function activity_list_dto_for_current_project($filterParams = [])
    {
        $projectModel = ProjectModel::getById($this->projectId);
        return ActivityListDto::getActivityForOneProject($projectModel, $this->userId, $filterParams);
    }

    public function activity_list_dto_for_lexical_entry($entryId, $filterParams = [])
    {
        $projectModel = ProjectModel::getById($this->projectId);
        return ActivityListDto::getActivityForOneLexEntry($projectModel, $entryId, $filterParams);
    }

    /*
     * --------------------------------------------------------------- SCRIPTUREFORGE ---------------------------------------------------------------
     */

    // ---------------------------------------------------------------
    // PROJECT API
    // ---------------------------------------------------------------
    /**
     * Update an Sfchecks Project
     *
     * @param array $settings
     * @return string $projectId of written object
     */
    public function project_update($settings)
    {
        return SfchecksProjectCommands::updateProject($this->projectId, $this->userId, $settings);
    }

    public function project_updateUserRole($userId, $role)
    {
        return ProjectCommands::updateUserRole($this->projectId, $userId, $role);
    }

    public function project_acceptJoinRequest($userId, $role)
    {
         UserCommands::acceptJoinRequest($this->projectId, $userId, $this->website, $role);
         ProjectCommands::removeJoinRequest($this->projectId, $userId);
    }

    public function project_denyJoinRequest($userId)
    {
        ProjectCommands::removeJoinRequest($this->projectId, $userId);
    }

    // REVIEW: should this be part of the general project API ?
    public function project_removeUsers($userIds)
    {
        return ProjectCommands::removeUsers($this->projectId, $userIds);
    }

    /**
     * Read a project from the given $id
     *
     * @param string $id
     * @return array
     */
    public function project_read($id)
    {
        return ProjectCommands::readProject($id);
    }

    public function project_settings()
    {
        return ProjectSettingsDto::encode($this->projectId, $this->userId);
    }

    /**
     * Updates the ProjectSettingsModel which are settings accessible only to site administrators
     * @param SmsSettings[] $smsSettingsArray
     * @param EmailSettings[] $emailSettingsArray
     * @return string $result id to the projectSettingsModel
     */
    public function project_updateSettings($smsSettingsArray, $emailSettingsArray)
    {
        return ProjectCommands::updateProjectSettings($this->projectId, $smsSettingsArray, $emailSettingsArray);
    }

    public function project_readSettings()
    {
        return ProjectCommands::readProjectSettings($this->projectId);
    }

    public function project_pageDto()
    {
        return ProjectPageDto::encode($this->projectId, $this->userId);
    }

    // ---------------------------------------------------------------
    // MESSAGE API
    // ---------------------------------------------------------------
    public function message_markRead($messageId)
    {
        return MessageCommands::markMessageRead($this->projectId, $messageId, $this->userId);
    }

    public function message_send($userIds, $subject, $emailTemplate, $smsTemplate)
    {
        return MessageCommands::sendMessage($this->projectId, $userIds, $subject, $smsTemplate, $emailTemplate, '');
    }

    // ---------------------------------------------------------------
    // TEXT API
    // ---------------------------------------------------------------
    public function text_update($object)
    {
        return TextCommands::updateText($this->projectId, $object, $this->userId);
    }

    public function text_read($textId)
    {
        return TextCommands::readText($this->projectId, $textId);
    }

    public function text_archive($textIds)
    {
        return TextCommands::archiveTexts($this->projectId, $textIds);
    }

    public function text_publish($textIds)
    {
        return TextCommands::publishTexts($this->projectId, $textIds);
    }

    public function text_settings_dto($textId)
    {
        return TextSettingsDto::encode($this->projectId, $textId, $this->userId);

    }

    public function text_exportComments($params)
    {
        return ParatextExport::exportCommentsForText($this->projectId, $params['textId'], $params);
    }

    // ---------------------------------------------------------------
    // Question / Answer / Comment API
    // ---------------------------------------------------------------
    public function question_update($object)
    {
        return QuestionCommands::updateQuestion($this->projectId, $object, $this->userId);
    }

    public function question_read($questionId)
    {
        return QuestionCommands::readQuestion($this->projectId, $questionId);
    }

    public function question_archive($questionIds)
    {
        return QuestionCommands::archiveQuestions($this->projectId, $questionIds);
    }

    public function question_publish($questionIds)
    {
        return QuestionCommands::publishQuestions($this->projectId, $questionIds);
    }

    public function question_update_answer($questionId, $answer)
    {
        return QuestionCommands::updateAnswer($this->projectId, $questionId, $answer, $this->userId);
    }

    public function question_update_answerExportFlag($questionId, $answerId, $isToBeExported)
    {
        return QuestionCommands::updateAnswerExportFlag($this->projectId, $questionId, $answerId, $isToBeExported);
    }

    public function question_update_answerTags($questionId, $answerId, $tags)
    {
        return QuestionCommands::updateAnswerTags($this->projectId, $questionId, $answerId, $tags);
    }

    public function question_remove_answer($questionId, $answerId)
    {
        return QuestionCommands::removeAnswer($this->projectId, $questionId, $answerId);
    }

    public function question_update_comment($questionId, $answerId, $comment)
    {
        return QuestionCommands::updateComment($this->projectId, $questionId, $answerId, $comment, $this->userId);
    }

    public function question_remove_comment($questionId, $answerId, $commentId)
    {
        return QuestionCommands::removeComment($this->projectId, $questionId, $answerId, $commentId);
    }

    public function question_comment_dto($questionId)
    {
        return QuestionCommentDto::encode($this->projectId, $questionId, $this->userId);
    }

    public function question_list_dto($textId)
    {
        return QuestionListDto::encode($this->projectId, $textId, $this->userId);
    }

    public function answer_vote_up($questionId, $answerId)
    {
        return QuestionCommands::voteUp($this->userId, $this->projectId, $questionId, $answerId);
    }

    public function answer_vote_down($questionId, $answerId)
    {
        return QuestionCommands::voteDown($this->userId, $this->projectId, $questionId, $answerId);
    }

    // ---------------------------------------------------------------
    // QuestionTemplates API
    // ---------------------------------------------------------------
    public function questionTemplate_update($model)
    {
        return QuestionTemplateCommands::updateTemplate($this->projectId, $model);
    }

    public function questionTemplate_read($id)
    {
        return QuestionTemplateCommands::readTemplate($this->projectId, $id);
    }

    public function questionTemplate_delete($questionTemplateIds)
    {
        return QuestionTemplateCommands::deleteQuestionTemplates($this->projectId, $questionTemplateIds);
    }

    public function questionTemplate_list()
    {
        return QuestionTemplateCommands::listTemplates($this->projectId);
    }

    // ---------------------------------------------------------------
    // Upload API
    // ---------------------------------------------------------------
    public function sfChecks_uploadFile($mediaType, $tmpFilePath)
    {
        $response = SfchecksUploadCommands::uploadFile($this->projectId, $mediaType, $tmpFilePath);
        return JsonEncoder::encode($response);
    }

    /*
     * --------------------------------------------------------------- LANGUAGEFORGE ----------------------------------------------------------------
     */

    // ---------------------------------------------------------------
    // PROJECT API
    // ---------------------------------------------------------------
    /**
     * Update a lexicon Project
     *
     * @param array $settings
     * @return string $projectId of written object
     */
    public function lex_project_update($settings)
    {
        return LexProjectCommands::updateProject($this->projectId, $this->userId, $settings);
    }

    public function lex_baseViewDto()
    {
        return LexBaseViewDto::encode($this->projectId, $this->userId);
    }

    public function lex_projectDto()
    {
        return LexProjectDto::encode($this->projectId);
    }

    public function lex_dbeDtoFull($browserId, $offset)
    {
        $sessionLabel = 'lexDbeFetch_' . $browserId;
        $this->app['session']->set($sessionLabel, time());

        return LexDbeDto::encode($this->projectId, $this->userId, null, $offset);
    }

    public function lex_dbeDtoUpdatesOnly($browserId, $lastFetchTime = null)
    {
        throw new Exception('This is an exception');
        $sessionLabel = 'lexDbeFetch_' . $browserId;
        if ($lastFetchTime == null) {
            $lastFetchTime = $this->app['session']->get($sessionLabel);
        }
        $this->app['session']->set($sessionLabel, time());
        if ($lastFetchTime) {
            $lastFetchTime = $lastFetchTime - 5; // 5 second buffer

            return LexDbeDto::encode($this->projectId, $this->userId, $lastFetchTime);
        } else {
            return LexDbeDto::encode($this->projectId, $this->userId);
        }
    }

    public function lex_configuration_update($config, $optionlists)
    {
        if (!LexProjectCommands::updateConfig($this->projectId, $config)) return false;
        foreach ($optionlists as $optionlist) {
            LexOptionListCommands::updateList($this->projectId, $optionlist);
        }
        return true;
    }

    public function lex_project_removeMediaFile($mediaType, $fileName)
    {
        return LexUploadCommands::deleteMediaFile($this->projectId, $mediaType, $fileName);
    }

    public function lex_entry_read($entryId)
    {
        return LexEntryCommands::readEntry($this->projectId, $entryId);
    }

    public function lex_entry_update($model)
    {
        return LexEntryCommands::updateEntry($this->projectId, $model, $this->userId);
    }

    public function lex_entry_remove($entryId)
    {
        return LexEntryCommands::removeEntry($this->projectId, $entryId, $this->userId);
    }

    public function lex_comment_update($data)
    {
        return LexCommentCommands::updateComment($this->projectId, $this->userId, $this->website, $data);
    }

    public function lex_commentReply_update($commentId, $data)
    {
        return LexCommentCommands::updateReply($this->projectId, $this->userId, $this->website, $commentId, $data);
    }

    public function lex_comment_delete($commentId)
    {
        return LexCommentCommands::deleteComment($this->projectId, $this->userId, $this->website, $commentId);
    }

    public function lex_commentReply_delete($commentId, $replyId)
    {
        return LexCommentCommands::deleteReply($this->projectId, $this->userId, $this->website, $commentId, $replyId);
    }

    public function lex_comment_plusOne($commentId)
    {
        return LexCommentCommands::plusOneComment($this->projectId, $this->userId, $commentId);
    }

    public function lex_comment_updateStatus($commentId, $status)
    {
        return LexCommentCommands::updateCommentStatus($this->projectId, $commentId, $status);
    }

    public function lex_optionlists_update($params)
    {
        return LexOptionListCommands::updateList($this->projectId, $params);
    }

    public function lex_uploadAudioFile($mediaType, $tmpFilePath)
    {
        $response = LexUploadCommands::uploadAudioFile($this->projectId, $mediaType, $tmpFilePath);
        return JsonEncoder::encode($response);
    }

    public function lex_uploadImageFile($mediaType, $tmpFilePath)
    {
        $response = LexUploadCommands::uploadImageFile($this->projectId, $mediaType, $tmpFilePath);
        return JsonEncoder::encode($response);
    }

    public function lex_upload_importProjectZip($mediaType, $tmpFilePath)
    {
        $response = LexUploadCommands::importProjectZip($this->projectId, $mediaType, $tmpFilePath);
        return JsonEncoder::encode($response);
    }

    public function lex_upload_importLift($mediaType, $tmpFilePath)
    {
        $response = LexUploadCommands::importLiftFile($this->projectId, $mediaType, $tmpFilePath);
        return JsonEncoder::encode($response);
    }

    // ---------------------------------------------------------------
    // Send and Receive API
    // ---------------------------------------------------------------
    public function sendReceive_getUserProjects($username, $password)
    {
        return SendReceiveCommands::getUserProjects($username, $password);
    }

    public function sendReceive_updateSRProject($srProject)
    {
        return SendReceiveCommands::updateSRProject($this->projectId, $srProject);
    }

    public function sendReceive_receiveProject()
    {
        SendReceiveCommands::queueProjectForSync($this->projectId);
        return SendReceiveCommands::startLFMergeIfRequired($this->projectId);
    }

    public function sendReceive_commitProject()
    {
        return SendReceiveCommands::startLFMergeIfRequired($this->projectId);
    }

    public function sendReceive_getProjectStatus()
    {
        return SendReceiveCommands::getProjectStatus($this->projectId);
    }

    public function sendReceive_notification_receiveRequest($projectCode)
    {
        return SendReceiveCommands::notificationReceiveRequest($projectCode);
    }

    public function sendReceive_notification_sendRequest($projectCode)
    {
        return SendReceiveCommands::notificationSendRequest($projectCode);
    }


    /*
     * --------------------------------------------------------------- TRANSLATION MANAGER API ---------------------------------------------------------------
     */
    /**
     * @param array $projectData
     * @return string $projectId
     */
    public function translate_projectUpdate($projectData)
    {
        return TranslateProjectCommands::updateProject($this->projectId, $this->userId, $projectData);
    }

    /**
     * @return array
     */
    public function translate_projectDto()
    {
        return TranslateProjectDto::encode($this->projectId, $this->userId);
    }

    /**
     * @param array $configData
     * @return string $projectId
     */
    public function translate_configUpdate($configData)
    {
        if (array_key_exists('userPreferences', $configData)) {
            $this->translate_configUpdateUserPreferences($configData['userPreferences']);
        }

        return TranslateProjectCommands::updateConfig($this->projectId, $configData);
    }

    /**
     * @param array $userPreferenceData
     * @return string $projectId
     */
    public function translate_configUpdateUserPreferences($userPreferenceData)
    {
        return TranslateProjectCommands::updateUserPreferences($this->projectId, $this->userId, $userPreferenceData);
    }

    public function translate_documentSetUpdate($documentSetData)
    {
        return TranslateDocumentSetCommands::updateDocumentSet($this->projectId, $documentSetData);
    }

    public function translate_documentSetListDto()
    {
        return TranslateDocumentSetDto::encode($this->projectId, $this->userId);
    }

    public function translate_documentSetRemove($documentId)
    {
        return TranslateDocumentSetCommands::removeDocumentSet($this->projectId, $documentId);
    }

    public function translate_updateMetrics($metricData, $documentSetId, $metricId)
    {
        return TranslateMetricCommands::updateMetric($this->projectId, $metricId, $metricData, $documentSetId, $this->userId);
    }

    public function translate_usxToHtml($usx)
    {
        $usxHelper = new UsxHelper($usx, true);
        return $usxHelper->toHtml();
    }


    /*
     * --------------------------------------------------------------- SEMANTIC DOMAIN TRANSLATION MANAGER API ---------------------------------------------------------------
     */
    public function semdom_editor_dto($browserId, $lastFetchTime = null)
    {
        $sessionLabel = 'lexDbeFetch_' . $browserId;
        $this->app['session']->set($sessionLabel, time());
        if ($lastFetchTime) {
            $lastFetchTime = $lastFetchTime - 5; // 5 second buffer

            return SemDomTransEditDto::encode($this->projectId, $this->userId, $lastFetchTime);
        } else {
            return SemDomTransEditDto::encode($this->projectId, $this->userId);
        }
    }

    public function semdom_get_open_projects() {
        return SemDomTransProjectCommands::getOpenSemdomProjects($this->userId);
    }

    public function semdom_item_update($data) {
        return SemDomTransItemCommands::update($data, $this->projectId);
    }

    public function semdom_project_exists($languageIsoCode) {
        return SemDomTransProjectCommands::checkProjectExists($languageIsoCode);
    }

    public function semdom_workingset_update($data) {
        return SemDomTransWorkingSetCommands::update($data, $this->projectId);
    }

    public function semdom_export_project() {
        return $this->website->domain . "/" . SemDomTransProjectCommands::exportProject($this->projectId);
    }

    // 2015-04 CJH REVIEW: this method should be moved to the semdom project commands (and a test should be written around it).  This method should also assert that a project with that code does not already exist
    public function semdom_create_project($languageIsoCode, $languageName, $useGoogleTranslateData) {
        return SemDomTransProjectCommands::createProject($languageIsoCode, $languageName, $useGoogleTranslateData, $this->userId, $this->website);
    }

    public function semdom_does_googletranslatedata_exist($languageIsoCode) {
        return SemDomTransProjectCommands::doesGoogleTranslateDataExist($languageIsoCode);
    }

    // -------------------------------- Project Management App Api ----------------------------------
    public function project_management_dto() {
        return ProjectManagementDto::encode($this->projectId);
    }

    public function project_management_report_sfchecks_userEngagementReport() {
        return SfchecksReports::UserEngagementReport($this->projectId);
    }

    public function project_management_report_sfchecks_topContributorsWithTextReport() {
        return SfchecksReports::TopContributorsWithTextReport($this->projectId);
    }

    public function project_management_report_sfchecks_responsesOverTimeReport() {
        return SfchecksReports::ResponsesOverTimeReport($this->projectId);
    }

    // -------------------------------- Semdomtrans App Management Api ----------------------------------
    public function semdomtrans_app_management_dto() {
        return SemDomTransAppManagementDto::encode();
    }

    public function semdomtrans_export_all_projects() {
        // TODO: implement this
        return ['exportUrl' => '/sampledownload.zip'];
    }

    // ---------------------------------------------------------------
    // Private Utility Functions
    // ---------------------------------------------------------------
    private static function isAnonymousMethod($methodName)
    {
        $methods = [
            'get_captcha_data',
            'reset_password',
            'sendReceive_getUserProjects',
            'sendReceive_notification_receiveRequest',
            'sendReceive_notification_sendRequest',
            'user_register',
            'user_register_oauth',
            'user_calculate_username',
            'check_unique_identity',
            'session_getSessionData'
        ];
        return in_array($methodName, $methods);
    }

    public function checkPermissions($methodName)
    {
        if (! self::isAnonymousMethod($methodName)) {
            if (! $this->userId) {
                throw new UserNotAuthenticatedException("Your session has timed out.  Please login again.");
            }
            try {
                $projectModel = ProjectModel::getById($this->projectId);
            } catch (\Exception $e) {
                $projectModel = null;
            }
            $rightsHelper = new RightsHelper($this->userId, $projectModel, $this->website);
            if (! $rightsHelper->userCanAccessMethod($methodName)) {
                throw new UserUnauthorizedException("Insufficient privileges accessing API method '$methodName'");
            }
        }
    }

    public function update_last_activity($newtime = null)
    {
        if (is_null($newtime)) {
            // Default to current time
            $newtime = time();
        }
        $this->app['session']->set('last_activity', $newtime);
    }
}
