# MkApi

## API Development with Laravel MKAPI

MkApi is a Laravel-based tool designed to streamline the creation of API-related files, including models, controllers, and Swagger documentation. This project simplifies API development by providing a set of commands to generate the necessary files with Swagger support.

Here’s the updated **Packages Used** section with your provided packages. I've included descriptions and versions for clarity:

---

## Packages Used

This project utilizes the following packages to enhance functionality and streamline development:

| Package Name                          | Description                                                                 | Version       |
|---------------------------------------|-----------------------------------------------------------------------------|---------------|
| `php-open-source-saver/jwt-auth`      | JWT (JSON Web Token) authentication for Laravel APIs.                       | ^2.7          |
| `darkaonline/l5-swagger`              | Swagger API documentation generator for Laravel.                            | ^8.6          |
| `laravel-persian-validation`          | Persian-specific validation rules for Laravel applications.                 | -             |
| `larastan/larastan`                   | Static analysis tool for Laravel applications to detect issues in code.     | ^3.0          |
| `spatie/laravel-backup`               | Backup tool for Laravel applications, including databases and files.        | ^9.1          |

---

### How to Install Packages

You can install these packages using Composer. Run the following command to install all dependencies:

```bash
composer install
```

---

## Installation

### Install the MkApi Tool

Run the following command to install the MkApi tool:

```bash
php artisan install:mkapi
```

---

## Key Commands

### Generate Base Controller with Swagger Support

To create a base controller with Swagger support, use the following command:

```bash
php artisan mkapi:baseControllerSWG
```

---

### Generate Model with Swagger Support

To generate a model with Swagger support, use the command below. Replace `YOUR_MODEL_NAME` with the desired model name.

```bash
php artisan mkapi:ModelSWG --name=YOUR_MODEL_NAME
```

---

### Generate Controller with Swagger Support

To create a controller with Swagger support, use the following command. Replace `YOUR_CONTROLLER_NAME` with the desired controller name.

```bash
php artisan mkapi:ControllerSWG --name=YOUR_CONTROLLER_NAME
```

---

## Command Options

### `--force`

Overwrite existing files using this option:

```bash
php artisan mkapi:ModelSWG --name=YOUR_MODEL_NAME --force
```

---

### `--backup`

Add backup packages to the project:

```bash
php artisan install:mkapi --backup
```

---

### `--iran`

Add Persian-specific packages (e.g., `laravel-persian-validation`) to the project:

```bash
php artisan install:mkapi --iran
```

---

### `--code`

Add Swagger documentation and code to controllers:

```bash
php artisan mkapi:ControllerSWG --name=YOUR_CONTROLLER_NAME --code
```

---

## Project Setup

1. Ensure Laravel is installed on your system.
2. Install the MkApi tool using the following command:

   ```bash
   php artisan install:mkapi
   ```

3. Generate models and controllers for your project using the relevant commands:

   ```bash
   php artisan mkapi:ModelSWG --name=ProductModel
   php artisan mkapi:ControllerSWG --name=ProductController
   ```

---

## Contributing

We welcome contributions! If you have suggestions or improvements, feel free to:

- Create an [Issue](https://github.com/mk990/MkApi/issues).
- Submit a [Pull Request](https://github.com/mk990/MkApi/pulls).

---

## Contributors

- [mk990](https://github.com/mk990)
- [Emad Shirzad](https://github.com/Emadshirzad)

---
