<?php

namespace App\Controller;

use App\Entity\Url;
use App\Entity\UrlStats;
use App\Form\UrlType;
use App\Repository\UrlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class UrlController extends AbstractController
{
    #[Route('/admin/url', name: 'url_index', methods: ['GET'])]
    public function index(UrlRepository $urlRepository): Response
    {

        return $this->render('admin/url/index.html.twig', [
            'urls' => $urlRepository->findAll(),
        ]);
    }

    #[Route('/admin/url/new', name: 'url_new', methods: ['GET', 'POST'])]
    public function newurl(Request $request): Response
    {
        $url = new Url();
        $form = $this->createForm(UrlType::class, $url);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($url);
            $entityManager->flush();

            return $this->redirectToRoute('url_index');
        }

        return $this->render('admin/url/new.html.twig', [
            'url' => $url,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/url/{id}', name: 'url_show', methods: ['GET'])]
    public function show(Url $url): Response
    {
        return $this->render('admin/url/show.html.twig', [
            'url' => $url,
        ]);
    }

    #[Route('/admin/url/{id}/edit', name: 'url_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Url $url): Response
    {
        $form = $this->createForm(UrlType::class, $url);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('url_index');
        }

        return $this->render('admin/url/edit.html.twig', [
            'url' => $url,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/url/{id}', name: 'url_delete', methods: ['DELETE'])]
    public function delete(Request $request, Url $url): Response
    {
        if ($this->isCsrfTokenValid('delete' . $url->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($url);
            $entityManager->flush();
        }

        return $this->redirectToRoute('url_index');
    }


    #[Route('/url/create', name: 'url_create')]
    public function create(Request $request, ValidatorInterface $validator): Response
    {

        $url = $request->get('url');
        $shortUrl = null;


        # url validation
        $constraints = new Assert\Collection([
            'url' => [new Assert\Url()]
        ]);

        $violations = $validator->validate([
            'url' => $url
        ], $constraints);

        $accessor = PropertyAccess::createPropertyAccessor();
        $errorMessages = [];

        foreach ($violations as $v) {
            $accessor->setValue($errorMessages, $v->getPropertyPath(), $v->getMessage());
        }

        if (count($errorMessages) === 0) {
            # generate 5 digit hash
            $alpha_numeric = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            $url_hash = substr(str_shuffle($alpha_numeric), 0, 5);


            $em = $this->getDoctrine()->getManager();
            $userId = $this->getUser() ? $this->getUser()->getId() : 1;

            $url_item = new Url();
            $url_item->setUrl($url)
                ->setUrlHash($url_hash)
                ->setCreatedAt((new \DateTime()))
                ->setUserId($userId)
                ->setClickCount(0)
                ->setIsPublic(true)
                ->setExpiredAt((new \DateTime()))
                ->setIsActive(true);

            $em->persist($url_item);
            $em->flush();

            $shortUrl = $_SERVER['SERVER_NAME'] . "/" . $url_hash;
        }


        return new JsonResponse([
            'success' => count($errorMessages) === 0 ?? false,
            'response' => $shortUrl,
            'error' => count($errorMessages) > 0 ?? false,
            'errorMessage' => count($errorMessages) > 0 ? $errorMessages : null
        ], 200);

    }


    #[Route('/{urlHash}', name: 'redirector')]
    public function redirector($urlHash, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        $urlRepository = $em->getRepository(Url::class);

        $url_item = $urlRepository->findOneBy([
            'is_active' => true,
            'urlHash' => $urlHash
        ]);

        if ($url_item) {
            $url = $url_item->getUrl();
            $urlId = $url_item->getId();
            $url_stats = new UrlStats();
            $url_item->setClickCount($url_item->getClickCount() + 1);
            $url_item->setStats(

                $url_stats->setUrlId($urlId)
                ->setBrowser($this->getBrowser())
                ->setIpAddress($request->getClientIp())
                ->setDevice($this->getOS())
                ->setResolution('-')
                ->setLocale( 'tr')
                ->setCity('istanbul')
                ->setCountry('turkey')
                ->setCreatedAt( ( new \DateTime() ))
        );
            $this->getDoctrine()->getManager()->flush();

            return $this->redirect($url);
        }

        return $this->redirectToRoute('home');
    }

    public function saveStats($urlId, Request $request ): UrlStats
    {


   /*     $clientIp = $request->getClientIp();

        $em = $this->getDoctrine()->getManager();

        $url_stats = new UrlStats();
        $url_stats->setUrlId($urlId)
            ->setBrowser($this->getBrowser())
            ->setIpAddress($clientIp)
            ->setDevice($this->getOS())
            ->setResolution('-')
            ->setLocale( 'tr')
            ->setCity('istanbul')
            ->setCountry('turkey')
            ->setCreatedAt( ( new \DateTime() ));

        $em->persist($url_stats);
        $em->flush();*/
    }


    public function getOS()
    {

        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $os_platform = "Bilinmeyen İşletim Sistemi";
        $os_array = array(
            '/windows nt 10/i' => 'Windows 10',
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i' => 'Mac OS 9',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile'
        );

        foreach ($os_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $os_platform = $value;
            }
        }
        return $os_platform;
    }


   public function getBrowser()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $browser = "Bilinmeyen Tarayıcı";
        $browser_array = array(
            '/msie/i' => 'Internet Explorer',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/chrome/i' => 'Chrome',
            '/edge/i' => 'Edge',
            '/opera/i' => 'Opera',
            '/netscape/i' => 'Netscape',
            '/maxthon/i' => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i' => 'Handheld Browser'
        );

        foreach ($browser_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $browser = $value;
            }
        }
        return $browser;
    }


}
