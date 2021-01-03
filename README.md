# users authentication

1. Create sql database and connect it to your Codeigniter 4 application
2. Paste 'Commands' folder inside the 'app' directory
3. Add next few terminal commands - 

```
- php spark users
- php spark migrate
- php spark db:seed Users
```
4. Inside the app/Controllers/BaseController directory add following helper in helpers array - ```protected $helpers = ['checkuser'];```
![](screen-01.png)
5. Use helper function to check if user is logged in or it have a privilegies - \App\Helpers\CheckUser::loggedin()

Note:
Logged in user is stored inside session storage with user ID - ```session()->get('loggedin')```. If key not exists - null will be returned.


# Routing urls
At the end following routes going to be available - 

- https://sitename.com/users/login
- https://sitename.com/users/logout
- https://sitename.com/users/registration
- https://sitename.com/users/account
- https://sitename.com/users/validation

You can find tham on **App/Controllres/Users.php**

# Views
Views are generated corresponding to the **Users** controller. You can find tham on **App/Views** directory.

# Helper function
There is a helper function available by which you can chack whether users is logged in, or do logged in user have a privilegies declared in usersgroup table.

```
\App\Helpers\CheckUser::loggedin();
// OR
\App\Helpers\CheckUser::loggedin([2,3]);
```