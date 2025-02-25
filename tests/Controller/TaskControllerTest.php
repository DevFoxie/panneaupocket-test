<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Tests\DatabasePrimer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskControllerTest extends WebTestCase
{

    use DatabasePrimer;

    private $client;
    private $entityManager;
    private $taskRepository;
    private $testTask;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $this->primeDatabase($this->entityManager);

        $this->taskRepository = $this->entityManager->getRepository(Task::class);


        $this->prepareDatabase();
    }

    private function prepareDatabase(): void
    {
        // Nettoyer les tâches existantes pour le test
        $tasks = $this->taskRepository->findAll();
        foreach ($tasks as $task) {
            $this->entityManager->remove($task);
        }
        $this->entityManager->flush();

        // Créer une tâche de test
        $task = new Task();
        $task->setTitle('Tâche de test');
        $task->setDescription('Description de test');
        $task->setStatus('À faire');

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->testTask = $task;
    }

    public function testIndex(): void
    {
        $this->client->request('GET', '/task/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Liste des tâches');
    }

    public function testNew(): void
    {
        $crawler = $this->client->request('GET', '/task/new');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'task[title]' => 'Nouvelle tâche',
            'task[description]' => 'Description de la nouvelle tâche',
            'task[status]' => 'À faire'
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/task/');
    }

    public function testEdit(): void
    {

        $this->assertNotNull($this->testTask->getId(), "L'ID de la tâche de test ne doit pas être null");


        $crawler = $this->client->request('GET', '/task/' . $this->testTask->getId() . '/edit');


        $this->assertResponseIsSuccessful();

        $html = $crawler->html();

        // file_put_contents('debug-edit.html', $html);

        $buttons = $crawler->filter('button')->each(function ($node) {
            return $node->text();
        });

        $form = $crawler->filter('form')->form();

        $formValues = [
            'task[title]' => 'Tâche modifiée',
            'task[description]' => 'Description modifiée',
            'task[status]' => 'En cours'
        ];

        $this->client->submit($form, $formValues);

        $this->assertResponseRedirects('/task/');

        $this->client->followRedirect();

        $this->assertSelectorExists('.alert-success');

        $updatedTask = $this->taskRepository->find($this->testTask->getId());
        $this->assertEquals('Tâche modifiée', $updatedTask->getTitle());
    }

    public function testUpdateStatus(): void
    {
        $crawler = $this->client->request('GET', '/task/');

        // Trouver le formulaire de statut pour notre tâche de test
        $formSelector = sprintf('form[action="/task/%s/status"]', $this->testTask->getId());

        // Afficher un message utile si le sélecteur ne trouve rien
        if ($crawler->filter($formSelector)->count() === 0) {
            $this->fail(sprintf('Formulaire non trouvé avec le sélecteur: %s', $formSelector));
        }

        $form = $crawler->filter($formSelector)->form([
            'task_status[status]' => 'Terminée'
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/task/');
    }

    public function testDelete(): void
    {
        $crawler = $this->client->request('GET', '/task/');

        // Récupérer le token CSRF du formulaire
        $tokenSelector = sprintf('form[action="/task/%s"] input[name="_token"]', $this->testTask->getId());

        if ($crawler->filter($tokenSelector)->count() === 0) {
            $this->fail(sprintf('Token CSRF non trouvé avec le sélecteur: %s', $tokenSelector));
        }

        $token = $crawler->filter($tokenSelector)->attr('value');

        // Soumettre la demande de suppression
        $this->client->request('POST', '/task/' . $this->testTask->getId(), [
            '_token' => $token
        ]);

        $this->assertResponseRedirects('/task/');
    }
}
