# Simple Plugin Guide

> **Build a WordPress plugin without Service Providers - perfect for small projects**

This guide shows you how to create a simple WordPress plugin using Sitechips Core without the complexity of Service Providers. We'll build a **Todo List Manager** as our example.

## 🎯 When to Use This Approach

The simple approach is perfect for:
- ✅ Small plugins (< 1000 lines of code)
- ✅ Quick prototypes and MVPs
- ✅ Learning Sitechips Core basics
- ✅ Plugins with 1-3 features
- ✅ Internal tools and utilities

Consider the [Advanced Plugin](advanced-plugin.md) approach if you need:
- ❌ Multiple features or modules
- ❌ Team collaboration
- ❌ Extensive customization hooks
- ❌ Complex integrations

## 📁 Project Structure

```
my-todo-list/
├── my-todo-list.php      # Main plugin file
├── composer.json         # Dependencies
├── README.md            # Documentation
│
├── lib/                 # Sitechips Core (copy from boilerplate)
│
├── src/                 # Your plugin code
│   ├── TodoRepository.php
│   ├── TodoController.php
│   └── TodoListTable.php
│
├── templates/           # View templates
│   ├── admin-page.php
│   └── todo-form.php
│
├── assets/             # CSS/JS files
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
│
└── tests/              # Unit tests
    └── Unit/
        └── TodoRepositoryTest.php
```

## 🚀 Step 1: Setup

### Create composer.json

```json
{
    "name": "mycompany/my-todo-list",
    "description": "Simple todo list manager for WordPress",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Furgo\\Sitechips\\Core\\": "lib/",
            "MyCompany\\TodoList\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "MyCompany\\TodoList\\Tests\\": "tests/"
        }
    }
}
```

### Install dependencies

```bash
composer install
```

## 🔨 Step 2: Main Plugin File

Create `my-todo-list.php`:

```php
<?php
/**
 * Plugin Name: My Todo List
 * Plugin URI: https://example.com/my-todo-list
 * Description: A simple todo list manager built with Sitechips Core
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: my-todo-list
 */

declare(strict_types=1);

namespace MyCompany\TodoList;

use Furgo\Sitechips\Core\Plugin\PluginFactory;

// Prevent direct access
defined('ABSPATH') || exit;

// Check dependencies
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>My Todo List:</strong> Please run <code>composer install</code>.';
        echo '</p></div>';
    });
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Create plugin with inline service definitions
$plugin = PluginFactory::create(__FILE__, [
    // Configuration
    'db.table' => $GLOBALS['wpdb']->prefix . 'my_todos',
    'per_page' => 20,
    
    // Register services directly (no Service Providers)
    'todo.repository' => function($container) {
        return new TodoRepository(
            $GLOBALS['wpdb'],
            $container->get('db.table')
        );
    },
    
    'todo.controller' => function($container) {
        return new TodoController(
            $container->get('todo.repository'),
            $container->get('plugin')
        );
    },
    
    'assets.manager' => function($container) {
        return new \Furgo\Sitechips\Core\Services\AssetManager(
            $container->get('plugin.url'),
            $container->get('plugin.version')
        );
    }
]);

// Initialize when WordPress is ready
add_action('plugins_loaded', function() use ($plugin) {
    $plugin->boot();
    
    // Register hooks directly (no Service Provider boot phase)
    if (is_admin()) {
        add_action('admin_menu', function() use ($plugin) {
            add_menu_page(
                'My Todo List',
                'Todo List',
                'manage_options',
                'my-todo-list',
                function() use ($plugin) {
                    $plugin->get('todo.controller')->renderAdminPage();
                },
                'dashicons-list-view',
                30
            );
        });
        
        add_action('admin_enqueue_scripts', function($hook) use ($plugin) {
            if ($hook !== 'toplevel_page_my-todo-list') {
                return;
            }
            
            $assets = $plugin->get('assets.manager');
            $assets->addScript('my-todo-admin', 'assets/js/admin.js', ['jquery'])
                   ->addStyle('my-todo-admin', 'assets/css/admin.css')
                   ->enqueue();
        });
    }
});

// Database setup on activation
register_activation_hook(__FILE__, function() use ($plugin) {
    $plugin->get('todo.repository')->createTable();
    $plugin->dispatch('activated');
});

// Cleanup on deactivation
register_deactivation_hook(__FILE__, function() use ($plugin) {
    $plugin->dispatch('deactivating');
    // Optionally clean up data
});
```

## 💾 Step 3: Repository Class

Create `src/TodoRepository.php`:

```php
<?php
declare(strict_types=1);

namespace MyCompany\TodoList;

class TodoRepository
{
    public function __construct(
        private \wpdb $db,
        private string $table
    ) {}
    
    public function createTable(): void
    {
        $charset = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            completed tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY completed (completed)
        ) $charset;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public function findAll(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE completed = 0' : '';
        
        return $this->db->get_results(
            "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC"
        );
    }
    
    public function find(int $id): ?object
    {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );
    }
    
    public function create(array $data): int
    {
        $this->db->insert($this->table, [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'completed' => 0
        ]);
        
        return $this->db->insert_id;
    }
    
    public function update(int $id, array $data): bool
    {
        return false !== $this->db->update(
            $this->table,
            $data,
            ['id' => $id]
        );
    }
    
    public function delete(int $id): bool
    {
        return false !== $this->db->delete(
            $this->table,
            ['id' => $id]
        );
    }
    
    public function toggleComplete(int $id): bool
    {
        $todo = $this->find($id);
        if (!$todo) {
            return false;
        }
        
        return $this->update($id, [
            'completed' => !$todo->completed
        ]);
    }
}
```

## 🎮 Step 4: Controller

Create `src/TodoController.php`:

```php
<?php
declare(strict_types=1);

namespace MyCompany\TodoList;

use Furgo\Sitechips\Core\Plugin\Plugin;

class TodoController
{
    public function __construct(
        private TodoRepository $repository,
        private Plugin $plugin
    ) {}
    
    public function renderAdminPage(): void
    {
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFormSubmission();
        }
        
        // Handle actions
        if (isset($_GET['action'])) {
            $this->handleAction();
        }
        
        // Get todos
        $todos = $this->repository->findAll();
        
        // Render template
        include $this->plugin->get('plugin.path') . 'templates/admin-page.php';
    }
    
    private function handleFormSubmission(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'add_todo')) {
            wp_die('Invalid nonce');
        }
        
        $title = sanitize_text_field($_POST['todo_title'] ?? '');
        $description = sanitize_textarea_field($_POST['todo_description'] ?? '');
        
        if (empty($title)) {
            $this->plugin->logError('Todo title is required');
            return;
        }
        
        $id = $this->repository->create([
            'title' => $title,
            'description' => $description
        ]);
        
        if ($id) {
            $this->plugin->dispatch('todo.created', $id);
            $this->plugin->log('Todo created: ' . $title);
            
            wp_redirect(add_query_arg([
                'page' => 'my-todo-list',
                'message' => 'created'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    private function handleAction(): void
    {
        $action = $_GET['action'];
        $id = (int) ($_GET['id'] ?? 0);
        $nonce = $_GET['_wpnonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'todo_action')) {
            wp_die('Invalid nonce');
        }
        
        switch ($action) {
            case 'complete':
                if ($this->repository->toggleComplete($id)) {
                    $this->plugin->dispatch('todo.completed', $id);
                }
                break;
                
            case 'delete':
                if ($this->repository->delete($id)) {
                    $this->plugin->dispatch('todo.deleted', $id);
                }
                break;
        }
        
        wp_redirect(remove_query_arg(['action', 'id', '_wpnonce']));
        exit;
    }
}
```

## 🎨 Step 5: Templates

Create `templates/admin-page.php`:

```php
<?php
/**
 * @var array $todos
 */
?>
<div class="wrap">
    <h1 class="wp-heading-inline">My Todo List</h1>
    <a href="#" class="page-title-action" onclick="document.getElementById('add-todo-form').style.display='block'; return false;">
        Add New
    </a>
    
    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Todo item created successfully!</p>
        </div>
    <?php endif; ?>
    
    <div id="add-todo-form" style="display: none;">
        <form method="post" class="todo-form">
            <?php wp_nonce_field('add_todo'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="todo_title">Title</label></th>
                    <td>
                        <input type="text" 
                               id="todo_title" 
                               name="todo_title" 
                               class="regular-text" 
                               required>
                    </td>
                </tr>
                <tr>
                    <th><label for="todo_description">Description</label></th>
                    <td>
                        <textarea id="todo_description" 
                                  name="todo_description" 
                                  rows="3" 
                                  class="large-text"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Add Todo</button>
                <button type="button" class="button" onclick="this.form.parentElement.style.display='none';">
                    Cancel
                </button>
            </p>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($todos as $todo): ?>
                <tr class="<?php echo $todo->completed ? 'completed' : ''; ?>">
                    <td>
                        <strong><?php echo esc_html($todo->title); ?></strong>
                    </td>
                    <td><?php echo esc_html($todo->description); ?></td>
                    <td>
                        <?php if ($todo->completed): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Completed
                        <?php else: ?>
                            <span class="dashicons dashicons-clock"></span> Pending
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($todo->created_at); ?></td>
                    <td>
                        <?php $nonce = wp_create_nonce('todo_action'); ?>
                        <a href="<?php echo add_query_arg([
                            'page' => 'my-todo-list',
                            'action' => 'complete',
                            'id' => $todo->id,
                            '_wpnonce' => $nonce
                        ], admin_url('admin.php')); ?>" 
                           class="button button-small">
                            <?php echo $todo->completed ? 'Reopen' : 'Complete'; ?>
                        </a>
                        <a href="<?php echo add_query_arg([
                            'page' => 'my-todo-list',
                            'action' => 'delete',
                            'id' => $todo->id,
                            '_wpnonce' => $nonce
                        ], admin_url('admin.php')); ?>" 
                           class="button button-small button-link-delete"
                           onclick="return confirm('Delete this todo?');">
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

## 🎨 Step 6: Styling

Create `assets/css/admin.css`:

```css
.todo-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wp-list-table tr.completed td {
    opacity: 0.6;
    text-decoration: line-through;
}

.wp-list-table .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
    vertical-align: middle;
    margin-right: 5px;
}
```

## 🔧 Step 7: Adding Features

### Add Settings

```php
// In main plugin file, add to service definitions
'settings' => function($container) {
    $settings = new \Furgo\Sitechips\Core\Services\Settings\SettingsManager('my_todo_settings');
    
    $settings->addSection('general', 'General Settings')
             ->addField('per_page', 'Items Per Page', 'general', 'number', [
                 'default' => 20,
                 'min' => 10,
                 'max' => 100
             ])
             ->addField('show_completed', 'Show Completed', 'general', 'checkbox', [
                 'label' => 'Show completed todos in list',
                 'default' => true
             ]);
    
    return $settings;
},

// Register settings
add_action('admin_init', function() use ($plugin) {
    $plugin->get('settings')->register();
});
```

### Add REST API

```php
// Add to hooks section
add_action('rest_api_init', function() use ($plugin) {
    register_rest_route('my-todo/v1', '/todos', [
        'methods' => 'GET',
        'callback' => function() use ($plugin) {
            return $plugin->get('todo.repository')->findAll();
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});
```

### Add Events

```php
// Listen to todo events
add_action('my-todo-list.todo.created', function($plugin, $todoId) {
    // Send notification email
    $todo = $plugin->get('todo.repository')->find($todoId);
    wp_mail(
        get_option('admin_email'),
        'New Todo Created',
        'New todo: ' . $todo->title
    );
}, 10, 2);
```

## 🧪 Step 8: Testing

Create `tests/Unit/TodoRepositoryTest.php`:

```php
<?php
namespace MyCompany\TodoList\Tests\Unit;

use MyCompany\TodoList\TodoRepository;
use PHPUnit\Framework\TestCase;

class TodoRepositoryTest extends TestCase
{
    private TodoRepository $repository;
    private \wpdb $wpdb;
    
    protected function setUp(): void
    {
        // Mock wpdb
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        $this->repository = new TodoRepository($this->wpdb, 'wp_my_todos');
    }
    
    public function testCreateTodo(): void
    {
        $this->wpdb->expects($this->once())
                   ->method('insert')
                   ->with('wp_my_todos', [
                       'title' => 'Test Todo',
                       'description' => 'Test Description',
                       'completed' => 0
                   ])
                   ->willReturn(1);
        
        $this->wpdb->insert_id = 123;
        
        $id = $this->repository->create([
            'title' => 'Test Todo',
            'description' => 'Test Description'
        ]);
        
        $this->assertEquals(123, $id);
    }
}
```

## 🚀 Step 9: Distribution

### Build for release

```bash
# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Create ZIP
zip -r my-todo-list.zip . \
    -x "*.git*" \
    -x "tests/*" \
    -x "composer.lock" \
    -x "*.md"
```

## 💡 Tips & Best Practices

### DO's ✅

```php
// Keep it simple
$plugin->get('service')->doSomething();

// Use type hints
public function __construct(
    private TodoRepository $repository,
    private Plugin $plugin
) {}

// Handle errors gracefully
try {
    $result = $plugin->get('api')->request();
} catch (Exception $e) {
    $plugin->logError($e->getMessage());
}
```

### DON'Ts ❌

```php
// Don't use globals
global $my_plugin; // ❌

// Don't skip nonce verification
if ($_POST) { // ❌ No nonce check!
    // Process form
}

// Don't mix concerns
class TodoRepository {
    public function renderForm() {} // ❌ Repository shouldn't render
}
```

## 🎯 When to Upgrade to Advanced

Consider moving to the [Advanced Plugin](advanced-plugin.md) approach when:

1. **Code exceeds 1000 lines** - Service Providers help organize
2. **Multiple developers** - Clear structure aids collaboration
3. **Need modularity** - Features as separate providers
4. **Complex integrations** - Better dependency management
5. **Reusable components** - Share providers between projects

## 🏁 Conclusion

You've built a complete WordPress plugin using Sitechips Core's simple approach! This pattern works great for small to medium plugins while still providing:

- ✅ Dependency injection
- ✅ Event system
- ✅ Logging
- ✅ Asset management
- ✅ Testability

Next steps:
- Add more features to your plugin
- Explore [Settings API](settings.md) integration
- Learn about [Testing](testing.md)
- When ready, graduate to [Advanced Plugin](advanced-plugin.md) patterns

---

Continue to [**Advanced Plugin Guide**](advanced-plugin.md) →