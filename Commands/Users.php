<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Users extends BaseCommand {

    protected $group       = 'Users controller';
    protected $name        = 'users';
    protected $description = 'Displays basic application information.';

    public function run(array $params) {
        
        if (empty($params)) {

            $this->usersController();
        }
    }


    private function usersController() {
        file_put_contents(APPPATH . 'Controllers/Users.php', '<?php namespace App\Controllers; 

use App\Models\UsersModel;
use CodeIgniter\Cache\Handlers\RedisHandler;
use phpDocumentor\Reflection\DocBlock\Tags\See;

class Users extends BaseController {

    private $usersModel;
    private $validation;
    private $email;

    public function __construct() {

        $this->usersModel = new UsersModel();
        $this->validation = \Config\Services::validation();

        // Email config
        $this->email = \Config\Services::email();
        $this->email->initialize([
            \'SMTPHost\'      => \'mail.sitename.com\', // Email HOST
            \'SMTPUser\'      => \'mail@address.com\', // Set email address
            \'SMTPPass\'      => \'password\', // Email account password
            \'SMTPPort\'      => \'443\', // Set email port
            \'mailType\'      => \'html\', // If sending HTML
            \'SMTPCrypto\'    => \'ssl\' // If using SSL
        ]);
    }


    public function index() {
        return redirect()->to(base_url(\'users/login\'));
    }


    public function login() {

        // Check if usre is logged in
        if (session()->get(\'loggedin\'))
            return redirect()->to(base_url(\'users/account\'));

        helper([\'form\', \'html\']);
        $errors = null;

        // If request type is post
        if ($this->request->getMethod() == \'post\') {

            $isValid = $this->validate([
                \'email\' => \'required|string|min_length[5]|max_length[40]|valid_email\',
                \'password\' => \'required|string|min_length[5]|max_length[60]\',
            ]);

            // Check if form is valid
            if ($isValid) {

                // Check if user exists
                $user = $this->usersModel->where(\'email\', $this->request->getPost(\'email\'))->find();
                
                // Check if user has been found
                if ($user) {

                    // Reset result
                    $user = reset($user);

                    // Check if user is activated
                    if ($user->activated) {

                        // Check user password 
                        $password = $this->request->getPost(\'password\');
                        if (password_verify($password, $user->password)) {
                            // Create session
                            session()->set(\'loggedin\', $user->id);

                            // Redirect to user account
                            return redirect()->to(base_url(\'users/account\'));
                        } else {
                            $errors = [\'User or password is incorrect\'];
                        }
                    } else {
                        $errors = [\'User is not activated. Check your mailbox for activating your account\'];
                    }
                } else {
                    $errors = [\'User or password is incorrect\'];
                }
            } else {
                $errors = $this->validation->getErrors();
            }
        }

        return view(\'login\', [
            \'errors\' => $errors
        ]);
    }


    public function logout() {

        session()->remove(\'loggedin\');
        return redirect()->to(base_url(\'users/login\'));
    }


    public function register() {

        helper([\'form\', \'html\']);

        $errors = null;

        if ($this->request->getMethod() == \'post\') {

            // Validate fields
            $isValid = $this->validate([
                \'name\' => \'required|string|min_length[3]|max_length[30]\',
                \'username\' => \'required|string|min_length[3]|max_length[30]\',
                \'email\' => \'required|string|min_length[5]|max_length[40]|valid_email\',
                \'password\' => \'required|string|min_length[5]|max_length[40]\',
                \'password_repeat\' => \'required|matches[password]\'
            ]);


            // If data is valid
            if ($isValid) {

                // Check if email is already taken
                $user = $this->usersModel->where(\'email\', $this->request->getPost(\'email\'))->first();
                if ($user) {
                    
                    $errors = [\'User already registered\'];
                } else {

                    // Create validateion key
                    $vkey = time();

                    // Set post data to variable
                    $data = $this->request->getPost();

                    // Prepare data for saving
                    $data[\'password\'] = password_hash($data[\'password\'], PASSWORD_DEFAULT);
                    $data[\'usersgroup\'] = 3;
                    $data[\'created_at\'] = time();
                    $data[\'updated_at\'] = time();
                    $data[\'vkey\'] = $vkey;
                    $this->usersModel->insert($data);

                    // Create validation url
                    $url = base_url(\'users/validation?key=\' . $vkey);

                    // Send validation key to entered email
                    $message = "<a href=\"{$url}\">Follow this link to validate your account</a>";

                    // Send email with validation key
                    $this->email->setFrom(\'owner@mail.com\', base_url());
                    $this->email->setTo($this->request->getPost(\'email\'));                    
                    $this->email->setSubject(\'Account validation\');
                    $this->email->setMessage($message);

                    // Send email
                    $this->email->send();

                    // Set success message
                    session()->setFlashdata(\'success\', \'Registration was successfull. Check your email for account activation.\');

                    // Redirect to login page
                    return redirect()->to(base_url(\'users/login\'));
                }

            } else {
                $errors = $this->validation->getErrors();
            }
        }


        return view(\'register\', [
            \'errors\' => $errors
        ]);
    }

    
    public function reset() {

        helper([\'form\', \'html\']);

        $errors = null;
        $success = null;

        // Check if post request
        if ($this->request->getMethod() == \'post\') {

            // Validate form
            $isValid = $this->validate([
                \'email\' => \'required|string|valid_email|min_length[5]|max_length[40]\',
                \'password\' => \'required|string|min_length[5]|max_length[60]\'
            ]);

            // Check if is valid
            if ($isValid) {

                // Fetch user
                $user = $this->usersModel->where(\'email\', $this->request->getPost(\'email\'));
                if ($user) {
                    
                    $vkey = time();
                    session()->set(\'vkey\', $vkey);
                    session()->set(\'password\', $this->request->getPost(\'password\'));

                    // Set validation key
                    $this->usersModel->update($user->id, [
                        \'vkey\' => $vkey,
                    ]);

                    // Create validation url
                    $url = base_url(\'users/validation?key=\' . $vkey);

                    // Create message
                    $message = "<a href=\"{$url}\">Follow this link to validate your account</a>";

                    // Send email with validation key
                    $this->email->setFrom(\'owner@mail.com\', base_url());
                    $this->email->setTo($this->request->getPost(\'email\'));                    
                    $this->email->setSubject(\'Password reset\');
                    $this->email->setMessage($message);

                    // Send email
                    $this->email->send();

                    // Apply success message
                    session()->setFlashdata(\'success\', \'Validation link has been sent to your email address. Check your mailbox\');

                    // Redirect to login page
                    return redirect()->to(base_url(\'users/login\'));

                } else {
                    $errors = [\'User not found\'];
                }
            } else {
                $errors = $this->validation->getErrors();
            }
        }

        return view(\'reset\', [
            \'errors\' => $errors,
            \'success\' => $success
        ]);
    }


    public function account() {

        // Check if user is logged in
        if (!session()->get(\'loggedin\'))
            return redirect()->to(base_url(\'users/login\'));

        helper([\'form\', \'html\']);

        // Variables
        $errors = null;
        $success = null;


        // Check if method is post
        if ($this->request->getMethod() == \'post\') {

            // Validate form
            $isValid = $this->validate([
                \'name\' => \'required|min_length[3]|max_length[30]\',
                \'username\' => \'required|min_length[3]|max_length[30]\',
                \'email\' => \'required|valid_email|min_length[5]|max_length[50]\'
            ]);

            // If is valid
            if ($isValid) {

                // Req data
                $data = $this->request->getPost();

                // User
                $user = $this->usersModel->find(session()->get(\'loggedin\'));

                // Password
                $password = $this->request->getPost(\'password\') ? password_hash($this->request->getPost(\'password\'), PASSWORD_DEFAULT) : $user->password;

                // User
                $this->usersModel->update(session()->get(\'loggedin\'), [
                    \'name\' => $data[\'name\'],
                    \'username\' => $data[\'username\'],
                    \'email\' => $data[\'email\'],
                    \'password\' => $password
                ]);

                $success = \'User updated successfully\';

            } else {
                $errors = $this->validation->getErrors();
            }
        }

        // Fetch user
        $user = $this->usersModel->find(session()->get(\'loggedin\'));

        // Render view
        return view(\'account\', [
            \'user\' => $user,
            \'errors\' => $errors,
            \'success\' => $success
        ]);
    }


    public function validation() {

        if ($this->request->getGet(\'key\')) {
            $key = $this->request->getGet(\'key\');

            // Check if validation key is not expired
            if ($key) {
                $user = $this->usersModel->where(\'vkey\', $key)->find();

                // Check if validation key is equals to user validation key stored in to the database
                if ($user) {

                    // Reset results
                    $user = reset($user);

                    // Update user to active user
                    $this->usersModel->update($user->id, [
                        \'activated\' => 1,
                        \'vkey\' => null,
                        \'password\' => session()->get(\'password\') ? password_hash(session()->get(\'password\'), PASSWORD_DEFAULT) : $user->password
                    ]);

                    // Delete vkey from and password from session
                    session()->remove(\'password\');

                    // Set success message
                    session()->setFlashdata(\'success\', \'Changes has been applied\');

                    // Redirect to login page
                    return redirect()->to(base_url(\'users/login\'));
                } else {
                    die(\'Invalid validatin key\');
                }
            } else {
                die(\'Validation key time has expired\');
            }
        } else {
            die(\'Validation key not found\');
        }
    }
}

        ');
        ///////////////////////// END OF THE USERS CONTROLLER /////////////////////////


        file_put_contents(APPPATH . 'Database/Seeds/Users.php', '<?php namespace App\Database\Seeds;

class Users extends \CodeIgniter\Database\Seeder
{
    public function run() {
        $items = [
            [
                \'groupid\' => 1,
                \'name\' => \'Super user\',
                \'description\' => \'God mode\'
            ],
            [
                \'groupid\' => 2,
                \'name\' => \'Manager\',
                \'description\' => \'Mid privilegies\'
            ],
            [
                \'groupid\' => 3,
                \'name\' => \'User\',
                \'description\' => \'Registered user\'
            ]
        ];

        // Using Query Builder
        foreach ($items as $item)
            $this->db->table(\'usersgroup\')->insert($item);
    }
}
        ');
        ///////////////////////// END OF THE USERS SEEDERS /////////////////////////




        file_put_contents(APPPATH . 'Database/Migrations/' . date('Y-m-d-') . rand(100000, 500000) . '_users.php', '<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Users extends Migration
{
    public function up() {
        $this->forge->addField([
            \'id\' => [
                \'type\' => \'INT\',
                \'unsigned\' => true,
                \'auto_increment\' => true,
            ],
            \'name\' => [
                \'type\' => \'VARCHAR\',
                \'constraint\' => 250
            ],
            \'username\' => [
                \'type\' => \'VARCHAR\',
                \'constraint\' => 250
            ],
            \'email\' => [
                \'type\' => \'VARCHAR\',
                \'constraint\' => 250
            ],
            \'password\' => [
                \'type\' => \'TEXT\'
            ],
            \'usersgroup\' => [
                \'type\' => \'INT\',
                \'constraint\' => 5
            ],
            \'avatar\' => [
                \'type\' => \'TEXT\',
                \'null\' => true
            ],
            \'activated\' => [
                \'type\' => \'INT\',
                \'constraint\' => 2
            ],
            \'vkey\' => [
                \'type\' => \'TEXT\',
                \'null\' => true
            ],
            \'created_at\' => [
                \'type\' => \'INT\',
                \'constraint\' => 5
            ],
            \'updated_at\' => [
                \'type\' => \'INT\',
                \'constraint\' => 5
            ],
        ]);
        $this->forge->addKey(\'id\', true);
        $this->forge->createTable(\'users\');
    }

    //--------------------------------------------------------------------

    public function down()
    {
        $this->forge->dropTable(\'users\');
    }
}
        ');
        ///////////////////////// END OF THE USERS MIGRATION /////////////////////////

        file_put_contents(APPPATH . 'Database/Migrations/' . date('Y-m-d-') . rand(100000, 500000) . '_usersgroup.php', '<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Usersgroup extends Migration
{
    public function up()
    {
        $this->forge->addField([
            \'id\' => [
                \'type\' => \'INT\',
                \'unsigned\' => true,
                \'auto_increment\' => true,
            ],
            \'groupid\' => [
                \'type\' => \'INT\',
                \'constraint\' => 2
            ],
            \'name\' => [
                \'type\' => \'VARCHAR\',
                \'constraint\' => 250,
                \'null\' => true
            ],
            \'description\' => [
                \'type\' => \'TEXT\',
                \'null\' => true
            ]
        ]);

        $this->forge->addKey(\'id\', true);
        $this->forge->createTable(\'usersgroup\');
    }

    //--------------------------------------------------------------------

    public function down()
    {
        $this->forge->dropTable(\'usersgroup\');
    }
}
        ');
        ///////////////////////// END OF THE USERSGRUOP MIGRATION /////////////////////////


        file_put_contents(APPPATH . 'Models/UsersModel.php', '<?php namespace App\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;

class UsersModel extends Model {
    protected $table = \'users\';
    protected $primaryKey = \'id\';

    protected $returnType = \'object\';
    protected $useSoftDeletes = false;

    protected $allowedFields = [\'id\', \'name\', \'username\', \'email\', \'password\', \'usersgroup\', \'avatar\', \'activated\', \'vkey\', \'created_at\', \'updated_at\'];

    protected $useTimestamps = false;
    protected $skipValidation = false;

    // Get user with related usersgroup
    public function user(int $id) {
        $user = $this->select(\'*\')
            ->join(\'usersgroup\', \'users.usersgroup = usersgroup.groupid\')
            ->where(\'users.id\', $id)
            ->findAll();
        
        return $user;
    }
}');
        ///////////////////////// END OF THE USERS MODEL /////////////////////////




        /////////////////////////// INSERT ACCOUNT ///////////////////////////
        file_put_contents(APPPATH . 'Views/account.php', '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- UIkit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/css/uikit.min.css" />

    <!-- UIkit JS -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit-icons.min.js"></script>

    <title>User profile</title>
</head>
<body>
    
    <section class="uk-section">
        <div class="uk-container uk-container-small">

            <h1>User profile</h1>

            <?php if ($errors): ?>
                <div class="uk-alert-danger" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <?php foreach ($errors as $e): ?>
                        <p><?= $e ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="uk-alert-success" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <p><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" accept-charset="utf-8" class="uk-child-width-1-2@s" uk-grid action="<?= current_url() ?>">

                <div>
                    <label for="" class="uk-form-label">Name</label>
                    <input class="uk-input" type="text" name="name" value="<?= $user->name ?>">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">Username</label>
                    <input class="uk-input" type="text" name="username" value="<?= $user->username ?>">
                </div>

                <div class="uk-width-1-1">
                    <label for="" class="uk-form-label">Email</label>
                    <input class="uk-input" type="text" name="email" value="<?= $user->email ?>">
                </div>
                
                <div class="uk-width-1-1">
                    <label for="" class="uk-form-label">Password</label>
                    <input class="uk-input" type="password" name="password" value="">
                </div>

                <div class="uk-width-1-1">
                    <button class="uk-button uk-button-primary uk-width-1-1" type="submit">Update account</button>
                </div>

            </form>
        </div>
    </section>

</body>
</html>');





        /////////////////////////// INSERT LOGIN ///////////////////////////
        file_put_contents(APPPATH . 'Views/login.php', '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- UIkit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/css/uikit.min.css" />

    <!-- UIkit JS -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit-icons.min.js"></script>

    <title>User login</title>
</head>
<body>
    
    <section class="uk-section">
        <div class="uk-container uk-container-small">

            <h1>User login</h1>


            <?php if ($errors): ?>
                <div class="uk-alert-danger" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <?php foreach ($errors as $e): ?>
                        <p><?= $e ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            
            <?php if (session()->getFlashdata(\'success\')): ?>
                <div class="uk-alert-success" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <p><?= session()->getFlashdata(\'success\') ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" accept-charset="utf-8" class="uk-child-width-1-1" uk-grid action="<?= current_url() ?>">

                <div>
                    <label for="" class="uk-form-label">Email</label>
                    <input class="uk-input" type="text" name="email" value="<?= set_value(\'email\') ?>">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">Password</label>
                    <input class="uk-input" type="password" name="password" value="">
                </div>

                <div class="uk-width-1-1">
                    <div class="uk-flex uk-flex-between uk-flex-middle">
                        <button class="uk-button uk-button-primary" type="submit">Login to account</button>

                        <div>
                            <a class="uk-margin-remove uk-padding-remove uk-link" href="<?= base_url(\'users/register\') ?>">Registration</a>
                            | 
                            <a href="<?= base_url(\'users/reset\') ?>">Reset password</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

</body>
</html>');



        /////////////////////////// INSERT REGISTER ///////////////////////////
        file_put_contents(APPPATH . 'Views/register.php', '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- UIkit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/css/uikit.min.css" />

    <!-- UIkit JS -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit-icons.min.js"></script>

    <title>User register</title>
</head>
<body>
    
    <section class="uk-section">
        <div class="uk-container uk-container-small">

            <h1>User registration</h1>

            <?php if ($errors): ?>
                <div class="uk-alert-danger" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <?php foreach ($errors as $e): ?>
                        <p><?= $e ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" accept-charset="utf-8" class="uk-child-width-1-2@s" uk-grid action="<?= current_url() ?>">

                <div>
                    <label for="" class="uk-form-label">Name</label>
                    <input class="uk-input" type="text" name="name" value="<?= set_value(\'name\') ?>">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">Username</label>
                    <input class="uk-input" type="text" name="username" value="<?= set_value(\'username\') ?>">
                </div>

                <div class="uk-width-1-1">
                    <label for="" class="uk-form-label">Email</label>
                    <input class="uk-input" type="text" name="email" value="<?= set_value(\'email\') ?>">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">Password</label>
                    <input class="uk-input" type="password" name="password" value="">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">Password repeat</label>
                    <input class="uk-input" type="password" name="password_repeat" value="">
                </div>

                <div class="uk-width-1-1">
                    <div class="uk-flex uk-flex-between uk-flex-middle">
                        <button class="uk-button uk-button-primary" type="submit">User registration</button>

                        <a class="uk-margin-remove uk-padding-remove uk-link" href="<?= base_url(\'users/login\') ?>">Already have an account</a>
                    </div>
                </div>

            </form>
        </div>
    </section>

</body>
</html>');


        /////////////////////////// INSERT RESET ///////////////////////////
        file_put_contents(APPPATH . 'Views/reset.php', '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- UIkit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/css/uikit.min.css" />

    <!-- UIkit JS -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.6.5/dist/js/uikit-icons.min.js"></script>

    <title>User login</title>
</head>
<body>
    
    <section class="uk-section">
        <div class="uk-container uk-container-small">

            <h1>Password reset</h1>

            <?php if ($errors): ?>
                <div class="uk-alert-danger" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <?php foreach ($errors as $e): ?>
                        <p><?= $e ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            
            <?php if (session()->getFlashdata(\'success\')): ?>
                <div class="uk-alert-success" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <p><?= session()->getFlashdata(\'success\') ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" accept-charset="utf-8" class="uk-child-width-1-1" uk-grid action="<?= current_url() ?>">

                <div>
                    <label for="" class="uk-form-label">Email</label>
                    <input class="uk-input" type="text" name="email" value="<?= set_value(\'email\') ?>">
                </div>
                
                <div>
                    <label for="" class="uk-form-label">New password</label>
                    <input class="uk-input" type="password" name="password" value="">
                </div>

                <div class="uk-width-1-1">
                    <div class="uk-flex uk-flex-between uk-flex-middle">
                        <button class="uk-button uk-button-primary" type="submit">Password reset</button>

                        <a class="uk-margin-remove uk-padding-remove uk-link" href="<?= base_url(\'users/login\') ?>">Back to login</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

</body>
</html>');


        /////////////////////////// USER HELPER FUNCTION ///////////////////////////
        file_put_contents(APPPATH . 'Helpers/checkuser_helper.php', '<?php namespace App\Helpers;

use App\Models\UsersModel;

class CheckUser {

    private static $userModel;

    public static function loggedin(array $privileges = []) {

        // Init user model
        $userModel = new UsersModel();

        if (session()->get(\'loggedin\')) {
            if (empty($privileges)) {

                // Get user ID
                $id = session()->get(\'loggedin\');

                // Select user
                return $userModel->user($id);

            } else {

                // User ID
                $userID = session()->get(\'loggedin\');

                // Get user by ID
                $user = $userModel->user($userID);

                if ($user)
                    $user = reset($user);
                else
                    return 0;

                if (in_array($user->groupid, $privileges))
                    return $user;
                else
                    return 0;
            }
        } else {
            return 0;
        }
    }
}');

    }
}