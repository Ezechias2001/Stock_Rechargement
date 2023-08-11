<?php

namespace App\Controller;

use Twilio\Rest\Client;
use App\Entity\Recharge;
use App\Form\RechargeType;
use App\Repository\RechargeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('')]    
class RechargeController extends AbstractController
{   
    function extract($text) {
        $pattern = '/DebitcomptecautionMontant(\d+)FCFAAgence([^Ref]+)Ref([^Code]+)Code(\d+)Date:(\d{2}-\d{2}-\d{4})a(\d{2}:\d{2}:\d{2})GMT\+00:00/';
        preg_match($pattern, $text, $matches);
    
        if (count($matches) === 7) {
            $montant = $matches[1];
            $agence = trim($matches[2]);
            $ref = trim($matches[3]);
            $code = $matches[4];
            $date = $matches[5];
            $heure = $matches[6];
    
            $result = array(
                'Montant' => $montant,
                'Agence' => $agence,
                'Ref' => $ref,
                'Code' => $code,
                'Date' => $date,
                'Heure' => $heure
            );
    
            return $result;
        }
    
        return false; // Retourne false si les informations n'ont pas été trouvées dans le texte.
    }
    

    #[Route('/', name: 'app_recharge_index', methods: ['GET'])]
    public function index(RechargeRepository $rechargeRepository): Response
    {   
        return $this->render('recharge/index.html.twig', [
            'recharges' => $rechargeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_recharge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recharge = new Recharge();
        $form = $this->createForm(RechargeType::class, $recharge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $text = $form->getData()->getTexte();

            $chaine_formatee = str_replace("\n", " ", $text);
            $chaine_formatee = str_replace(" ", "", $chaine_formatee);

            $informations = $this->extract($chaine_formatee); 

            $recharge->setAgence($informations['Agence']);
            $recharge->setMontant($informations['Montant']);
            $recharge->setReference($informations['Ref']);            
            $recharge->setCode($informations['Code']);           
            $recharge->setDate($informations['Date']);           
            $recharge->setHeure($informations['Heure']); 
            $entityManager->persist($recharge);
            $entityManager->flush();

            $this->addFlash('success', 'Votre formulaire a été soumis avec succès.');

            return $this->redirectToRoute('app_recharge_index', [], Response::HTTP_SEE_OTHER);
         } 
        //else if (!$form->isValid()) {
        //     $this->addFlash('error', "Rogner de l'image te tel sorte que seul le contenu du message soit visible.");

        // } 
        return $this->render('recharge/new.html.twig', [
            'recharge' => $recharge,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recharge_show', methods: ['GET'])]
    public function show(Recharge $recharge): Response
    {   
        
        return $this->render('recharge/show.html.twig', [
            'recharge' => $recharge,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recharge_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recharge $recharge, EntityManagerInterface $entityManager): Response
    {   
        $sid = "AC477f92aacf5fb47dd9f5b46cf2e23824";
        $token = "559ca7e8e41d16f4bd9107d39d05657b";

        // Initialiser le client Twilio
        $twilio = new Client($sid, $token);

        $form = $this->createForm(RechargeType::class, $recharge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            if ($recharge->isEtat()) {
                // Envoyer un message WhatsApp
                try {
                    $message = $twilio->messages->create(
                        "whatsapp:+22997222210", // Destinataire (votre numéro WhatsApp)
                        array(
                            "from" => "whatsapp:+14155238886", // Votre numéro Twilio (numéro Twilio WhatsApp)
                            "body" => "la recharge ayant la référence ".$recharge->getReference()." vient d'être utilisé à l'instant"
                        )
                    );
        
                    // Afficher le SID du message pour le suivi
                    // return new Response('Message SID: ' . $message->sid);
                } catch (\Exception $e) {
                    return new Response('Une erreur s\'est produite : ' . $e->getMessage());
                }        
            }

            return $this->redirectToRoute('app_recharge_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recharge/edit.html.twig', [
            'recharge' => $recharge,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recharge_delete', methods: ['POST'])]
    public function delete(Request $request, Recharge $recharge, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recharge->getId(), $request->request->get('_token'))) {
            $entityManager->remove($recharge);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recharge_index', [], Response::HTTP_SEE_OTHER);
    }
}
