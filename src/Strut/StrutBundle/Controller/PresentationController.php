<?php
/**
 * Created by PhpStorm.
 * User: tcit
 * Date: 15/11/16
 * Time: 17:47
 */

namespace Strut\StrutBundle\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Strut\StrutBundle\Entity\Presentation;
use Strut\StrutBundle\Entity\Version;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PresentationController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @param Request $request
     * @return Response
     */
    public function getPresentations(Request $request) {
        $repository = $this->get('strut.presentation_repository');
        $presentations = $repository->findByUser($this->getUser());
        return $this->render(':default:presentations.html.twig', ['presentations' => $presentations]);
    }

    /**
     * @route("/versions/{presentation}", name="versions")
     * @param Presentation $presentation
     * @return JsonResponse
     */
    public function getPresentationVersions(Presentation $presentation) {
        $versions = $presentation->getVersions();
        $json = $this->get('jms_serializer')->serialize($versions, 'json');
        return (new JsonResponse())->setJson($json);
    }

    /** API Stuff */

    /**
     * @route("/presentations-json", name="presentations-json")
     * @param Request $request
     * @return JSONResponse
     */
    public function getPresentationsJson(Request $request) {
        $repository = $this->get('strut.presentation_repository');
        $presentations = $repository->findByUser($this->getUser());
        $json = $this->get('jms_serializer')->serialize($presentations, 'json');
        return (new JsonResponse())->setJson($json);
    }

    /**
     * @route("/presentation/{title}", name="presentation")
     * @param Presentation $presentation
     * @return JsonResponse
     */
    public function getPresentationDataAction(Presentation $presentation) {
        $this->checkUserPresentationAction($presentation);
        $presentationData = $presentation->getLastVersion()->getContent();
        $json = $this->get('jms_serializer')->serialize($presentationData, 'json');
        return (new JsonResponse())->setJson($json);
    }

    /**
     * @param Presentation $presentation
     * @route("/delete-presentation/{title}", name="delete-presentation")
     * @return JsonResponse
     */
    public function deletePresentation(Presentation $presentation) {
        $this->checkUserPresentationAction($presentation);
        $em = $this->getDoctrine()->getManager();
        $em->remove($presentation);
        $em->flush();

        return new JsonResponse($presentation);
    }

    /**
     * @route("/delete-version/{version}", name="delete-version")
     * @param Version $version
     * @return JsonResponse
     */
    public function deleteVersion(Version $version) {
        $this->checkUserVersionAction($version);
        $em = $this->getDoctrine()->getManager();
        $version->getPresentation()->removeVersion($version);
        $em->remove($version);
        $em->flush();

        return new JsonResponse($version);
    }

    /**
     * @route("/restore-version/{version}", name="restore-version")
     * @param Version $version
     * @return JsonResponse
     */
    public function restoreVersion(Version $version) {
        $this->checkUserVersionAction($version);
        $em = $this->getDoctrine()->getManager();

        $presentation = $version->getPresentation();

        $newVersion = new Version($presentation);
        $newVersion->setContent($version->getContent());

        $presentation->addVersion($newVersion);

        $em->persist($newVersion);
        $em->flush();

        $versions = $presentation->getVersions();
        $json = $this->get('jms_serializer')->serialize($versions, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * @route("/new-presentation", name="new-presentation")
     * @param Request $request
     * @return JsonResponse
     */
    public function saveAction(Request $request) {
        $data = $request->get('data');
        $newEntry = (bool) $request->get('newEntry', 0);
        $name = $request->get('presentation');

        $em = $this->getDoctrine()->getManager();
        $logger = $this->get('logger');

        /** @var Presentation $presentation */
        $presentation = $this->get('strut.presentation_repository')->findOneBy(
            [
            'user' => $this->getUser(),
            'title' => $name,
            ]);

        if ($presentation && $data === $presentation->getLastVersion()->getContent()) {
            $logger->info("Tried to save but there's no change for presentation " . $presentation->getId());
            $json = $this->get('jms_serializer')->serialize($presentation, 'json');
            return new JsonResponse($json, 304, [], true);
        }

        $version = new Version();
        $version->setContent($data);
        $em->persist($version);
        $logger->info("Created version " . $version->getId());


        if ($presentation) { // If the presentation already exists, just add a new version
            $logger->info("Version  " . $version->getId() . " has been added to presentation " . $presentation->getId());
            $presentation->addVersion($version);
        } else if ($newEntry) { // otherwise, let's create an new presentation
            $presentation = new Presentation($this->getUser());
            $presentation->setTitle($name);
            $presentation->addVersion($version);
            $logger->info("A new presentation has been created " . $presentation->getTitle());
            $em->persist($presentation);
        } else {
            return new JsonResponse([]);
        }

        $em->flush();

        $json = $this->get('jms_serializer')->serialize($presentation, 'json');

        return (new JsonResponse())->setJson($json);

    }

    /**
     * @route("/save-preview/{title}", name="save-preview")
     * @param Request $request
     * @param Presentation $presentation
     * @return JsonResponse
     */
    public function savePreview(Request $request, Presentation $presentation) {
        //$this->checkUserAction($presentation);
        $previewData = $request->get('previewData');
        $presentation->setRendered($previewData);
        $em = $this->getDoctrine()->getManager();
        $em->flush();
        return new JSONResponse();

    }

    /**
     * @route("/preview/{title}/{type}/", name="preview")
     * @param Presentation $presentation
     * @param $type
     * @return Response
     */
    public function previewPresentationAction(Presentation $presentation, $type) {
        switch ($type) {
            case 'impress':
                return $this->render('@Strut/preview_export/impress.html', [
                    'presentation' => $presentation
                ]);

            case 'bespoke':
                return $this->render('@Strut/preview_export/bespoke.html', [
                    'presentation' => $presentation
                ]);

            case 'handouts':
                return $this->render('@Strut/preview_export/reveal.html', [
                    'presentation' => $presentation
                ]);

            default:
                return new JsonResponse(null, 406);
        }

    }

    /**
     * @route("/export-presentation/{presentation}", name="export-presentation")
     * @param Presentation $presentation
     * @return Response
     */
    public function exportPresentation(Presentation $presentation) {
        $response = new Response($presentation->getLastVersion()->getContent());

        $response->headers->set('Content-Type', 'text/plain');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $presentation->getTitle() . '.json'
        );

        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    /**
     * @route("/export-version/{version}", name="export-version")
     * @param Version $version
     * @return Response
     */
    public function exportVersion(Version $version) {
        $response = new Response($version->getContent());

        $response->headers->set('Content-Type', 'text/plain');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $version->getPresentation()->getTitle() . '-' . $version->getUpdatedAt()->format('d-m-Y H:i:s') . '.json'
        );

        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    /**
     * Check if the logged user can manage the given entry.
     *
     * @param Presentation $presentation
     */
    private function checkUserPresentationAction(Presentation $presentation)
    {
        if (null === $this->getUser() || $this->getUser()->getId() != $presentation->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can not access this presentation.');
        }
    }

    /**
     * Check if the logged user can manage the given entry.
     *
     * @param Version $version
     */
    private function checkUserVersionAction(Version $version)
    {
        if (null === $this->getUser() || $this->getUser()->getId() != $version->getPresentation()->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can not access this presentation.');
        }
    }

    /**
     * Get public URL for entry (and generate it if necessary).
     *
     * @param Presentation $presentation
     *
     * @Route("/share/{id}", requirements={"id" = "\d+"}, name="share")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shareAction(Presentation $presentation)
    {
        $this->checkUserPresentationAction($presentation);

        if (null === $presentation->getUuid()) {
            $presentation->generateUuid();

            $em = $this->getDoctrine()->getManager();
            $em->persist($presentation);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('share_entry', [
            'uuid' => $presentation->getUuid(),
        ]));
    }

    /**
     * Disable public sharing for an entry.
     *
     * @param Presentation $presentation
     *
     * @Route("/share/delete/{id}", requirements={"id" = "\d+"}, name="delete_share")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteShareAction(Presentation $presentation)
    {
        $this->checkUserPresentationAction($presentation);

        $presentation->cleanUuid();

        $em = $this->getDoctrine()->getManager();
        $em->persist($presentation);
        $em->flush();

        $json = $this->get('jms_serializer')->serialize($presentation, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * Ability to view a content publicly.
     *
     * @param Presentation $presentation
     *
     * @Route("/share/{uuid}", requirements={"uuid" = ".+"}, name="share_entry")
     * @Cache(maxage="25200", smaxage="25200", public=true)
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sharePresentationAction(Presentation $presentation)
    {
        return $this->render('@Strut/preview_export/impress.html', [
            'presentation' => $presentation
        ]);
    }
}
