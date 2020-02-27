<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MultiDump;

class ExampleController extends AbstractController
{
    /**
     * @Route("/", name="example")
     */
    public function index()
    {
        MultiDump::extend('primary', function ($kernel) {
            $doctrine = $kernel->getContainer()->get('doctrine');
            mdump($doctrine, 'secondary');

            $name = $doctrine->getName();
            return $name;
        }, 'Doctrine');

        $ary1 = ['foo' => 'bar'];
        $ary2 = ['foo2' => 'bar2'];

        mdump($ary1);
        mdump($ary2);
        mdump($this);

        return $this->render('base.html.twig');
    }
}