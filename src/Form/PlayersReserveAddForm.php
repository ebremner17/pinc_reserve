<?php

/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersReserveAddForm.php.
 */

namespace Drupal\pinc_reserve\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\pinc_reserve\Service\PlayersService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PlayersReserveAddForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   * @param \Drupal\players_reserve\Service\PlayersService $playersService
   *   The players service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * *   The messenger.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection                 $database,
    PlayersService             $playersService,
    MessengerInterface         $messenger
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->playersService = $playersService;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('pinc_reserve.players_service'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_reserve_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $date = NULL
  ) {

    // If the date is incorrect then redirect back to the reserve.
    if (!preg_match('/202[4-9]{1}[-][0-9]{1}[1-9]{1}-[0-3]{1}[0-9]{1}/', $date)) {
      return new RedirectResponse('/reserve');
    }

    if (
      $form_state->has('page_num') &&
      $form_state->get('page_num') == 2
    ) {

      return $this->playersReservePageTwo($form, $form_state);
    }

    // Set the form state page num, since if we arrive
    // here it is the first page of the form.
    $form_state->set('page_num', 1);

    // The form wrapper.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    $display_date = date('D M j, Y', strtotime($date));

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    // The date of the game.
    $form['wrapper']['date'] = [
      '#type' => 'hidden',
      '#default_value' => $date,
    ];

    // The phone number element.
    $form['wrapper']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('Enter your email address.'),
      '#required' => TRUE,
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['wrapper']['actions']['next'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Next'),
      '#submit' => ['::playersReserveNextSubmit'],
      '#validate' => ['::playersReserveNextValidate'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $page_values = $form_state->get('page_values');

    $this->messenger()->addMessage($this->t('The form has been submitted. name="@first @last", year of birth=@year_of_birth', [
      '@first' => $page_values['first_name'],
      '@last' => $page_values['last_name'],
      '@year_of_birth' => $page_values['birth_year'],
    ]));

    $this->messenger()->addMessage($this->t('And the favorite color is @color', ['@color' => $form_state->getValue('color')]));
  }

  /**
   * Provides custom validation handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextValidate(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the phone number from the form state.
    $email = $form_state->getValue('email');

    // Get the user ids based off the email.
    $uids = $this->getUserIds($email);

    // If there are no user ids, set an error.
    if (count($uids) == 0) {
      $form_state->setError(
        $form['wrapper']['email'],
        t('There is no player registered with that email address, try again, or <a href="/user/register">create a player account</a>.')
      );
    }
  }

  /**
   * Provides custom submission handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Set the values in the form state, setting
    // the page number to the second step.
    $form_state
      ->set('page_values', [
        'email' => $values['email'],
        'uid' => current($this->getUserIds($values['email'])),
        'date' => $values['date'],
      ])
      ->set('page_num', 2)
      ->setRebuild(TRUE);
  }

  /**
   * Builds the second step form (page 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function playersReservePageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    // Get the display date.
    $display_date = date('D M j, Y', strtotime($page_values['date']));

    // The wrapper for the form.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    $form += $this->getGamesFromNode($page_values);

    return $form;
  }

  /**
   * Provides custom validation handler for page 2.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextValidatePageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Ensure that email is correct.
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
      $form_state->setError(
        $form['email'],
        $this->t('Email is in invalid.')
      );
    }
  }

  /**
   * Provides custom submission handler for page 2.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextSubmitPageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get the page values, from previous page.
    $page_values = $form_state->get('page_values');

    // Get the uid from the values.
    $uid = $values['uid'];

    // Set the values in the form state, setting
    // the page number to the third step.
    $form_state
      ->set('page_values', [
        'uid' => $uid,
        'first_name' => $values['first_name'],
        'last_name' => $values['last_name'],
        'date' => $page_values['date'],
      ])
      ->set('page_num', 3)
      ->setRebuild(TRUE);
  }

  /**
   * Builds the second step form (page 3).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function playersReservePageThree(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values and page values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    // Get the node for the current game.
    $node = current(
      $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['title' => $page_values['date']])
    );

    // Load the games.
    $games = $this->playersService->getGames($node, FALSE, $page_values['uid']);

    // Reset the options and default values array.
    $options = [];
    $default_values = [];

    // Step through each of the games and add the
    // game title if the flag is set and get the
    // options.
    foreach ($games as $game) {

      // Set the options.
      $options[$game['title']] = $game['title'] . ': ' . $game['start_time'] . ' - ' . $game['end_time'];

      // If the flag for the user as being reserved is
      // set then add to the default values.
      if ($game['reserved_flag']) {
        $default_values[] = $game['title'];
      }
    }

    // The wrapper for the form.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    // The node id.
    $form['wrapper']['nid'] = [
      '#type' => 'hidden',
      '#default_value' => $node->id(),
    ];

    $display_date = date('D M j, Y', strtotime($page_values['date']));

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    // The games element for everything other than
    // Friday nights.
    $form['wrapper']['games'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Game types'),
      '#default_value' => $default_values,
    ];

    // The submit button.
    $form['wrapper']['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Reserve'),
      '#submit' => ['::playersReserveSubmit'],
    ];

    return $form;
  }

  /**
   * Provides custom submission handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    $node = $this->entityTypeManager->getStorage('node')
      ->load($values['nid']);

    // Get data from field.
    if ($paragraph_field_items = $node->get('field_games')->getValue()) {

      // Get storage. It very useful for loading a small number of objects.
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      // Collect paragraph field's ids.
      $ids = array_column($paragraph_field_items, 'target_id');

      // Load all paragraph objects.
      $paragraphs_objects = $paragraph_storage->loadMultiple($ids);

      foreach ($paragraphs_objects as $paragraph_object) {

        // Array to store current players.
        $players = [];

        // Get the current players.
        $current_players = $paragraph_object->get('field_game_players')->getValue();

        // Step through each of the current players and set in array.
        foreach ($current_players as $current_player) {
          $players[] = $current_player['value'];
        }

        // If registering player is not in the array, then add it.
        if (!in_array($page_values['uid'], $players)) {
          $players[] = $page_values['uid'];
        }

        // Set and save the players for the game.
        $paragraph_object->set('field_game_players', $players);
        $paragraph_object->save();
      }
    }

    // Add the message.
    $this->messenger->addStatus($this->t('You reservation has been successfully updated.'));

    $form_state->setRedirect('<front>');
  }

  /**
   * Function to get the games from the node.
   *
   * @param array $page_values
   *   The current page values.
   * @return array
   *   Array of games to get used in the form.
   */
  private function getGamesFromNode(array $page_values): array {

    // Get the node for the current game.
    $node = current(
      $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['title' => $page_values['date']])
    );

    // Load the games.
    $games = $this->playersService->getGames(
      $node,
      FALSE,
      $page_values['uid']
    );

    // Reset the options and default values array.
    $options = [];
    $default_values = [];

    // Step through each of the games and add the
    // game title if the flag is set and get the
    // options.
    foreach ($games as $game) {

      // Set the options.
      $options[$game['game_type']] = $game['title'] . ': ' . $game['start_time'] . ' start ';

      // If the flag for the user as being reserved is
      // set then add to the default values.
      if ($game['reserved_flag']) {
        $default_values[] = $game['game_type'];
      }
    }

    $form['game_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    // If there is a user, then add a welcome message.
    if (
      isset($page_values['uid']) &&
      $page_values['uid'] !== NULL
    ) {

      $user = User::load($page_values['uid']);
      $form['game_wrapper']['user_info'] = [
        '#type' => 'markup',
        '#markup' => 'Welcome, ' . $user->field_first_name->value . ' ' . $user->field_last_name->value,
      ];

      $form['uid'] = [
        '#type' => 'hidden',
        '#default_value' => $page_values['uid'],
      ];
    } else {
      $form['uid'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
    }

    // The node id.
    $form['game_wrapper']['nid'] = [
      '#type' => 'hidden',
      '#default_value' => $node->id(),
    ];

    // The games element for everything other than
    // Friday nights.
    $form['game_wrapper']['games'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Game types'),
      '#default_value' => $default_values,
    ];

    // Submit button.
    $form['game_wrapper']['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Reserve'),
      '#submit' => ['::playersReserveSubmit'],
    ];

    return $form;
  }

  /**
   * Function to get the user IDs based off email.
   *
   * @param string $email
   *   The email.
   *
   * @return array|int
   *   Array of ids or integer.
   */
  private function getUserIds(string $email) {

    // Query to get the user ids.
    $query = \Drupal::entityQuery('user');
    $query->accessCheck(FALSE);
    $query->condition('name', $email);

    return $query->execute();
  }
}
