<?php

namespace Drupal\pinc_reserve\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

/**
 * Class PlayersService.
 */
class PlayersService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTyperManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $account,
    MessengerInterface $messenger,
    Connection $database
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->account = $account;
    $this->messenger = $messenger;
    $this->database = $database;
  }

  /**
   * Function to get the correct date based on the time.
   *
   * @return string
   *   The correct date based on the time.
   */
  public function getCorrectDate(): string {

    // Todays time, need this to get what is the current
    // date.  If we are less than 4 am into the next day
    // the current date is yesterday.
    $time = date('G');

    // Get the correct date based on the time.
    if ($time >= 0 && $time <= 4) {
      return date('Y-m-d', strtotime("-1 days"));
    }
    else {
      return date('Y-m-d');
    }
  }

  /**
   * Function to get the node of the game by date.
   *
   * @param string $date
   *   The date for the node.
   * @return false|mixed|null
   *   The game node.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGameNodeByDate(string $date) {

    // Get the node based on the current date.
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_game_date' => $date]);

    if ($nodes) {
      return current($nodes);
    }
    else {
      return NULL;
    }
  }

  /**
   * Function to get the current user.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The user object.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentUser() {

    // Get the user id and the user object.
    $user_id = $this->account->id();
    return $this->entityTypeManager
      ->getStorage('user')
      ->load($user_id);
  }

  /**
   * Function to check if user is reserved.
   *
   * @param int $uid
   *   The user id.
   * @param int $nid
   *   The node id.
   *
   * @return mixed
   *  The user reservations.
   */
  public function checkUserReserved(int $uid, int $nid) {
    $query = $this->database->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id'])
      ->condition('pr.nid', $nid)
      ->condition('pr.uid', $uid);

    return $query->execute()->fetchAssoc();
  }

  /**
   * Function to get the games for a specific node.
   *
   * @param $node
   *   The node.
   * @param bool $remove_tourney_flag
   *   Flag to remove tournaments.
   *
   * @return array
   *   Array of games.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGames($node, $remove_tourney_flag = FALSE, int $uid = NULL) {

    if (!$node) {
      return [];
    }

    if (!$uid) {
      $uid = $this->account->id();
    }

    $games = [];

    // Get data from field.
    if ($paragraph_field_items = $node->get('field_games')->getValue()) {

      // Get storage. It very useful for loading a small number of objects.
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      // Collect paragraph field's ids.
      $ids = array_column($paragraph_field_items, 'target_id');

      // Load all paragraph objects.
      $paragraphs_objects = $paragraph_storage->loadMultiple($ids);

      if (date('G') <= 4) {
        $current_date = date( 'Y-m-d', strtotime( '-1 day'));
      }
      else {
        $current_date = date( 'Y-m-d');
      }

      // Step through each of the paragraph items and get
      // the game info.
      foreach ($paragraphs_objects as $paragraph) {

        // Get the game type field.
        $game_type = $paragraph->field_game_type->value;

        // If this has a remove tourney flag and it is a
        // tourney just skip it.
        if (
          $remove_tourney_flag &&
          str_contains($game_type, 'Tournament')
        ) {
          continue;
        }

        $list = '';
        $reserved_flag = FALSE;

        // If user is floor get the list.
        if ($this->isFloor()) {
          $list = $this->getList($node->id(), $game_type);
        }
        else {

//          $query = $this->database->select('players_reserve', 'pr')
//            ->fields('pr', ['reserve_id'])
//            ->condition('pr.nid', $node->id())
//            ->condition('pr.uid', $uid)
//            ->condition('pr.game_type', $game_type);
//
//          $result = $query->execute()->fetchAssoc();
//
//          if ($result) {
//            $reserved_flag = TRUE;
//          }
        }

        // If this is the current date, get the info about
        // the reserves.
        if ($node->field_game_date->getValue()[0]['value'] == $current_date) {

          // Get the start time.
          $start_time = $paragraph->field_pi_game_time->getValue()[0]['value'];
        }

        // Add the game to the games array.
        $games[] = [
          'title' => $this->getGameName($game_type),
          'game_type' => $game_type,
          'start_time' => $start_time,
          'reserved_flag' => $reserved_flag,
          'display_date' => date('l M j, Y', strtotime($current_date)),
        ];
      }
    }

    return $games;
  }

  /**
   * Function to get the info about the current reserve.
   *
   * @param Node $node
   *   The node.
   * @param string $game_type
   *   The game type.
   * @param bool $show_seated
   *   Flag to show the seated players.
   *
   * @return array
   *   The array of info about the reserve.
   */
  public function getCurrentReserveStats(Node $node, string $game_type, bool $show_seated) {

    // Get the number of reserved players.
    $query = $this->database
      ->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id'])
      ->condition('pr.nid', $node->id())
      ->condition('pr.game_type', $game_type)
      ->condition('pr.seated', 0)
      ->condition('pr.removed', 0)
      ->condition('pr.pleft', 0);
    $reserved = count($query->execute()->fetchAll());

    // Get the number of seated players.
    $query = $this->database
      ->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id'])
      ->condition('pr.nid', $node->id())
      ->condition('pr.game_type', $game_type)
      ->condition('pr.seated', 1);
    $seated = count($query->execute()->fetchAll());

    return [
      'reserved' => $reserved,
      'seated' => $show_seated ? $seated : NULL,
    ];
  }

  /**
   * Function to get the list of players.
   *
   * @param int $nid
   *   The node id.
   * @param string $game_type
   *   The game type.
   *
   * @return mixed
   *   The list of players.
   */
  public function getList(int $nid, string $game_type) {
    $query = $this->database
      ->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id', 'uid', 'first_name', 'last_name', 'reserve_time'])
      ->condition('pr.nid', $nid)
      ->condition('pr.game_type', $game_type)
      ->condition('pr.seated', 0)
      ->condition('pr.removed', 0)
      ->condition('pr.pleft', 0)
      ->orderBy('pr.reserve_time');

    return $query->execute()->fetchAll();
  }

  /**
   * Function to get if user has floor or admin role.
   *
   * @return bool
   *   If floor of admin.
   */
  public function isFloor(): bool {

    // Roles to check for.
    $roles = [
      'administrator',
      'floor',
    ];

    // Get the current user roles.
    $user_roles = $this->account->getRoles();

    // If user is floor or admin return TRUE.
    if(!empty(array_intersect($this->account->getRoles(), $roles))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Function to get the list of players.
   *
   * @return array
   *   An array of tournaments.
   */
  public function getTournaments(): array {

    // Ensure that we have something to return.
    $tourneys = [];

    // Get the query object.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();

    // Get the nids that are greater than today.
    $nids = $query->condition('title', date('Y-m-d', strtotime('now')), '>')
      ->condition('status', '1')
      ->condition('type', 'pi_ct_games')
      ->sort('title')
      ->execute();

    // Load all the nodes based on the nids.
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Step through each of the nodes and check if it
    // has a tournament.
    foreach ($nodes as $node) {

      // Get the games for that node.
      $games = $this->getGames($node);

      // Step through all the games and check if it
      // has a tournament.
      foreach ($games as $game) {

        // Resetting the game info array, so that we do not
        // get information that we do not want.
        $game_info = [];

        // If the node has a tournament then add to array.
        if (str_contains($game['title'], 'Tournament')) {

          $query = $this->database->select('players_reserve', 'pr')
            ->fields('pr', ['reserve_id'])
            ->condition('pr.nid', $node->id())
            ->condition('pr.uid', $this->account->id())
            ->condition('pr.game_type', $game['title']);

          $result = $query->execute()->fetchAssoc();

          $game_info['reserved_flag'] = $result ? TRUE : FALSE;

          $game_info['open_time'] = $game['start_time'] . ' - ' . $game['end_time'];

          // Get the game info value from the node.
          $info = $node->field_pi_game_info->getValue();

          // If there is a value add it to the game info.
          if ($info && isset($info[0]['value'])) {
            $game_info['info'] = [
              '#type' => 'processed_text',
              '#text' => $info[0]['value'],
              '#format' => $info[0]['format'],
            ];
          }

          // Get if the date is current.
          if ($this->getCorrectDate() == $node->field_game_date->value) {
            $game_info['current'] = TRUE;
          }
          else {
            $game_info['current'] = FALSE;
          }

          $game_info['games'] = [$game['title']];
          $game_info['game_day'] = date('l', strtotime($node->field_game_date->value));
          $game_info['game_date'] = date('M j, Y', strtotime($node->field_game_date->value));
          $game_info['date'] = $node->field_game_date->value;
          $game_info['title'] = $game['title'];
          $game_info['start_time'] = $game['start_time'];
          $game_info['end_time'] = $game['end_time'];
          $game_info['display_date'] = date('l F j, Y', strtotime($node->label()));

          $tourneys[] = $game_info;
        }
      }
    }

    return $tourneys;
  }

  /**
   * Function to get the current list of players.
   *
   * @param int $nid
   *   The node id.
   * @param string $game_type
   *   The type of game.
   *
   * @return array
   *   Array of current players.
   */
  public function getCurrentList(
    int $nid,
    string $game_type
  ) {

    $condition_or = new \Drupal\Core\Database\Query\Condition ('OR');

    $condition_or->condition('pr.seated', 1);

    $condition_or->condition('pr.pleft', 1);

    // The query to get the current list of players.
    $query = $this->database->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id', 'first_name', 'last_name', 'seated', 'pleft'])
      ->condition('pr.nid', $nid)
      ->condition('pr.game_type', $game_type)
      ->condition($condition_or)
      ->orderBy('pr.pleft')
      ->orderBy('last_name');

    $current_list = $query->execute()->fetchAll();

    return $current_list;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareResponsiveImage(
    ?Media $entity,
    string $image_style,
    string $field_name,
    bool $absolute_url = FALSE
  ): array {

    // Ensure that we can load an entity on the media.
    if ($entity && isset($entity->$field_name->entity)) {

      // Load in the file object if we have one.
      if ($file = $entity->$field_name->entity) {

        // Need to set these variables so that responsive image function,
        // has all the necessary info to process the image style.
        $variables['uri'] = $file->getFileUri();
        $variables['responsive_image_style_id'] = $image_style;

        // Set the alt for the image.
        $variables['alt'] = $entity->$field_name->alt;

        // This is a function from the responsive image module that sets all
        // the variables for the sources of the responsive image.
        template_preprocess_responsive_image($variables);

        // Step through each of the sources and setup our own sources array.
        foreach ($variables['sources'] as $source) {

          // If the absolute url flag is set, the get the absolute
          // url to the srcset, otherwise just the relative.
          if ($absolute_url) {
            $srcset = URL::fromUri('internal:' . $source->storage()['srcset']->value(), ['absolute' => TRUE])->toString();
          }
          else {
            $srcset = $source->storage()['srcset']->value();
          }

          $srcset_parts = explode('/', $srcset);

          foreach ($srcset_parts as $srcset_part) {
            if (strpos($srcset_part, 'ris_players') !== FALSE) {
              $style = $srcset_part;
              break;
            }
          }

          $variables['responsive_sources'][] = [
            'srcset' => $srcset,
            'media' => $source->storage()['media']->value(),
            'type' => $source->storage()['type']->value(),
            'style' => $style ?? NULL,
          ];
        }

        return $variables;
      }
    }

    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getResponsiveImageStyles(): array {
    return [
      'is_players_large',
      'is_players_medium',
      'is_players_small',
      'is_players_xsmall',
    ];
  }

  /**
   * Function to get the game info.
   *
   * @param Node $node
   *   The node.
   *
   * @return array
   *   Array of game info.
   */
  public function getGameInfo(Node $node): array {

    // Reset the games array or at least have a blank.
    $games = [];

    if (date('G') <= 4) {
      $current_date = date( 'Y-m-d', strtotime( '-1 day'));
    }
    else {
      $current_date = date( 'Y-m-d');
    }

    // Get data from field.
    if ($paragraph_field_items = $node->get('field_games')->getValue()) {
      // Get storage. It very useful for loading a small number of objects.
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      // Collect paragraph field's ids.
      $ids = array_column($paragraph_field_items, 'target_id');

      // Load all paragraph objects.
      $paragraphs_objects = $paragraph_storage->loadMultiple($ids);

      // Step through each of the paragraph items and get
      // the game info.
      foreach ($paragraphs_objects as $paragraph) {

        // Get the game type field.
        $game_type = $paragraph->field_game_type->value;

        // If this is the current date, get the info about
        // the reserves.
        if ($node->field_game_date->getValue()[0]['value'] == $current_date) {

          // Get the start time.
          $start_time = $paragraph->field_pi_game_time->getValue()[0]['value'];
        }

        $games[] = [
          'start_time' => $start_time,
          'game_type' => $game_type,
        ];
      }
    }

    // Set all the game info.
    $game_info['games'] = $games;
    $game_info['game_day'] = date('l', strtotime($node->field_game_date->value));
    $game_info['game_date'] = date('M j, Y', strtotime($node->field_game_date->value));
    $game_info['date'] = $node->field_game_date->value;

    return $game_info;
  }

  /**
   * Function to get the actual name of a game.
   *
   * @param string $machine_name
   *   The machine name of the selection.
   *
   * @return string|null
   *   The name of the game or null.
   */
  private function getGameName(string $machine_name): string|null {

    // Return the name of the game if it exists.
    return match ($machine_name) {
      '1_2_300_max' => '1/2 NLH ($300 max)',
      '2_5_plo' => '2/5 PLO ($800 max)',
      default => NULL,
    };

  }
}
