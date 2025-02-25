<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Form\TaskStatusType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TaskManager;

#[Route('/task')]
class TaskController extends AbstractController
{
    private TaskManager $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    #[Route('/', name: 'task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository): Response
    {
        $tasks = $taskRepository->findAllSorted();
        $statusForms = [];

        foreach ($tasks as $task) {
            $form = $this->createForm(TaskStatusType::class, $task, [
                'action' => $this->generateUrl('task_status_update', ['id' => $task->getId()]),
                'method' => 'POST',
            ]);

            $statusForms[$task->getId()] = $form->createView();
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'statusForm' => $statusForms,
        ]);
    }

    #[Route('/new', name: 'task_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taskManager->createTask($task);
            $this->addFlash('success', 'Tâche créée avec succès');

            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taskManager->updateTask($task);
            $this->addFlash('success', 'Tâche modifiée avec succès');

            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/status', name: 'task_status_update', methods: ['POST'])]
    public function updateStatus(Request $request, Task $task): Response
    {
        $form = $this->createForm(TaskStatusType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taskManager->updateTask($task);
            $this->addFlash('success', 'Statut de la tâche modifié avec succès');
        }

        return $this->redirectToRoute('task_index');
    }

    #[Route('/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task): Response
    {
        if ($this->isCsrfTokenValid('delete' . $task->getId(), $request->request->get('_token'))) {
            $this->taskManager->deleteTask($task);
            $this->addFlash('success', 'Tâche supprimée avec succès');
        }

        return $this->redirectToRoute('task_index');
    }
}
