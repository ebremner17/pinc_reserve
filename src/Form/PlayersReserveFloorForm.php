<?php

/**
 * @file
 * Contains \Drupal\pinc_reserve\Form\PlayersAddReserveForm.
 */

namespace Drupal\pinc_reserve\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\pinc_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

class PlayersReserveFloorForm extends FormBase {

  /**
   * The players service.
   *
   * @var \Drupal\players_reserve\Service\PlayersService
   */
  protected $playersService;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\pinc_reserve\Service\PlayersService $playersService
   *  The players service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    PlayersService $playersService,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager
  ) {

    $this->playersService = $playersService;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('pinc_reserve.players_service'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Checks access to the block add page for the block type.
   */
  public function access(AccountInterface $account) {

    // Get the user roles.
    $roles = $account->getRoles();

    // The list of allowed roles for the route.
    $allowed_roles = [
      'administrator',
      'floor',
    ];

    // Return access if user has correct role.
    if(array_intersect($roles, $allowed_roles)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_add_reserve_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $date = NULL
  ) {

    // Ensure that we have a tree for the form.
    $form['#tree'] = TRUE;

    // If there is no date supplied, get the current date.
    if (!$date) {
      $date = $this->playersService->getCorrectDate();
    }

    // Load the node based on the date.
    $node = current(
      $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['field_game_date' => $date]
        )
    );

    // Load the games for the date.
    $games = $this->playersService->getGames($node);

    // Wrapper for the list.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-contained-width', 'players-reserve'],
      ],
    ];

    // Set the date.
    $form['wrapper']['date'] = [
      '#markup' => '<h3>' . date('l M j, Y', strtotime($date)) . '</h3>',
    ];

    // Step through each of the games and get the form.
    foreach ($games as $game) {

      // The game type.
      $form['wrapper'][$game['title']] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['players-contained-width', 'players-reserve'],
        ],
      ];

      // Info about the game.
      $form['wrapper'][$game['title']]['game'] = [
        '#markup' => '<h4>' . $game['title'] . '</h4>',
      ];

      // If there is no list, set message.
      // If there is a list, process it.
      if (!$game['list']) {
        $form['wrapper'][$game['title']]['list'][$game['title']] = [
          '#type' => 'markup',
          '#markup' => 'There are currently no players reserved for this game.'
        ];
      }
      else {

        // We have a list so show the submit button.
        $show_submit_button = TRUE;

        // The type of game, hidden so we can use it
        // in the submit.
        $form['wrapper'][$game['game_type']]['game_type'] = [
          '#type' => 'hidden',
          '#value' => $game['title'],
        ];

        // The nid, hidden so we can use it
        // in the submit.
        $form['wrapper'][$game['game_type']]['nid'] = [
          '#type' => 'hidden',
          '#value' => $node->id(),
        ];

        $form['wrapper'][$game['game_type']]['details'] = [
          '#type' => 'details',
          '#title' => $this->t('List'),
        ];

        // The header for the table.
        $header = [
          ['data' => t('First Name')],
          ['data' => t('Last Name')],
        ];

        // The table for the list.
        $form['wrapper'][$game['game_type']]['details']['list'] = [
          '#type' => 'table',
          '#title' => 'Players',
          '#header' => $header,
        ];

        // Count for the number of players.
        $count = 0;

        // Step through and add players to list.
        foreach ($game['list'] as $player) {

          // Player first name.
          $form['wrapper'][$game['game_type']]['details']['list'][$count]['first_name'] = [
            '#type' => 'markup',
            '#markup' => $player['first_name'],
          ];

          // Player last name.
          $form['wrapper'][$game['game_type']]['details']['list'][$count]['last_name'] = [
            '#type' => 'markup',
            '#markup' => $player['last_name'],
          ];

          // Increment the counter.
          $count++;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
