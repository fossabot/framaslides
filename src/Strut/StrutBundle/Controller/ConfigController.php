<?php

namespace Strut\StrutBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Strut\GroupBundle\Entity\Group;
use Strut\GroupBundle\Entity\UserGroup;
use Strut\StrutBundle\Form\Type\ChangePasswordType;
use Strut\UserBundle\Form\Type\UserInformationType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Strut\StrutBundle\Entity\Config;
use Strut\StrutBundle\Form\Type\ConfigType;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ConfigController extends Controller
{
    /**
     * @param Request $request
     *
     * @Route("/config", name="config")
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $config = $this->getConfig();
        $userManager = $this->get('fos_user.user_manager');
        $user = $this->getUser();

        // handle basic config detail (this form is defined as a service)
        $configForm = $this->createForm(ConfigType::class, $config, ['action' => $this->generateUrl('config')]);
        $configForm->handleRequest($request);

        if ($configForm->isSubmitted() && $configForm->isValid()) {
            $em->persist($config);
            $em->flush();

            $request->getSession()->set('_locale', $config->getLanguage());

            $this->get('session')->getFlashBag()->add(
                'notice',
                'flashes.config.notice.config_saved'
            );

            return $this->redirect($this->generateUrl('config'));
        }


        // handle changing password
        $pwdForm = $this->createForm(ChangePasswordType::class, null, ['action' => $this->generateUrl('config').'#set4']);
        $pwdForm->handleRequest($request);

        if ($pwdForm->isSubmitted() && $pwdForm->isValid()) {
            $user->setPlainPassword($pwdForm->get('new_password')->getData());
            $userManager->updateUser($user, true);

            $this->get('session')->getFlashBag()->add('notice', 'flashes.config.notice.password_updated');

            return $this->redirect($this->generateUrl('config').'#set4');
        }

        // handle changing user information
        $userForm = $this->createForm(UserInformationType::class, $user, [
            'validation_groups' => ['Profile'],
            'action' => $this->generateUrl('config').'#set3',
        ]);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $userManager->updateUser($user, true);

            $this->get('session')->getFlashBag()->add(
                'notice',
                'flashes.config.notice.user_updated'
            );

            return $this->redirect($this->generateUrl('config').'#set3');
        }


        return $this->render('default/config.html.twig', [
            'form' => [
                'config' => $configForm->createView(),
                'pwd' => $pwdForm->createView(),
                'user' => $userForm->createView(),
                'enabled_users' => $this->getDoctrine()
                    ->getRepository('StrutUserBundle:User')
                    ->getSumEnabledUsers(),
            ],
        ]);
    }

    /**
     * Retrieve config for the current user.
     * If no config were found, create a new one.
     *
     * @return Config
     */
    private function getConfig()
    {
        $config = $this->getDoctrine()
            ->getRepository('Strut:Config')
            ->findOneByUser($this->getUser());

        // should NEVER HAPPEN ...
        if (!$config) {
            $config = new Config($this->getUser());
        }

        return $config;
    }

    /**
     * Delete account for current user.
     *
     * @Route("/account/delete", name="delete_account")
     *
     * @param Request $request
     *
     * @throws AccessDeniedException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAccountAction(Request $request)
    {
        $enabledUsers = $this->getDoctrine()
            ->getRepository('StrutUserBundle:User')
            ->getSumEnabledUsers();

        if ($enabledUsers <= 1) {
            throw new AccessDeniedException();
        }

        $user = $this->getUser();

        // logout current user
        $this->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        $em = $this->get('fos_user.user_manager');
        $em->deleteUser($user);

        return $this->redirect($this->generateUrl('fos_user_security_login'));
    }

    /**
     * Remove all presentations for the current user.
     *
     * @Route("/reset", name="config_reset")
     *
     * @return RedirectResponse
     */
    public function resetAction(): Response
    {
    	$em = $this->getDoctrine()->getManager();
        $presentations = $this->getUser()->getPresentations();
        $userGroups = $this->getUser()->getUserGroups();

        foreach ($presentations as $presentation) {
        	$em->remove($presentation);
		}

		foreach ($userGroups as $userGroup) {
        	/** @var UserGroup $userGroup */
        	if ($userGroup->getRole() == Group::ROLE_ADMIN) {
        		$group = $userGroup->getGroup();
        		if ($group->getAdmins()->count() === 1) {
        			$em->remove($group);
				}

			}
			$em->remove($userGroup);
		}

		$em->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            'flashes.config.notice.presentation_reset'
        );

        return $this->redirect($this->generateUrl('config').'#set3');
    }

	/**
	 * Switch view mode for current user.
	 *
	 * @Route("/config/view-mode", name="switch_view_mode")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function changeViewModeAction(Request $request)
	{
		$user = $this->getUser();
		$user->getConfig()->setListMode(!$user->getConfig()->getListMode());
		$em = $this->getDoctrine()->getManager();
		$em->persist($user);
		$em->flush();
		return $this->redirect($request->headers->get('referer'));
	}
}
