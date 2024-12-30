# RequestDatatableCriteria

## Description

`RequestDatatableCriteria` is an extension for the [prettus/l5-repository](https://github.com/andersao/l5-repository) package. It is designed to seamlessly integrate with the `ares-datatable` frontend component to handle server-side data filtering, sorting, searching, and pagination with minimal backend code. This package simplifies the interaction between the frontend and backend by adhering to a predefined request-response structure.

---

## Features

- **Filtering**: Automatically applies query-based filters (e.g., `?search=name:John`).
- **Sorting**: Supports ordering by specified fields (e.g., `?orderBy=name&sortedBy=asc`).
- **Pagination**: Handles paginated data with standard methods (e.g., `paginate()`).
- **Search Integration**: Includes support for partial matching using `like`.
- **Seamless Integration**: Works out-of-the-box with the `ares-datatable` frontend component.

---

## Install

To install the package, use Composer:

```bash
composer require arendach/request-datatable-criteria
```

---

## Usage

### Adding Criteria to a Repository

In your repository's `boot` method, add the `RequestDatatableCriteria`:

```php
use Arendach\RequestDatatableCriteria\RequestDatatableCriteria;

public function boot(): void
{
    $this->pushCriteria(app(RequestDatatableCriteria::class));
}
```

### Controller Example

Here's an example of integrating the package with a controller:

```php
use Illuminate\Http\Request;
use App\Repositories\Repository;
use Arendach\RequestDatatableCriteria\RequestDatatableCriteria;

class CustomerController extends Controller
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->repository->pushCriteria(app(RequestDatatableCriteria::class));
    }

    public function index(Request $request)
    {
        $customers = $this->repository->paginate();
        return response()->json($customers);
    }
}
```

---

## Configuration

The package requires no additional configuration. It works directly with Laravel's request object and `prettus/l5-repository`. However, you can extend or customize it based on your application's requirements.

---

## Example Request

### Query Parameters

- **Filtering**:
  ```
  GET /customers?search=name:John
  ```
  Filters customers where the `name` contains "John".

- **Sorting**:
  ```
  GET /customers?orderBy=created_at&sortedBy=desc
  ```
  Retrieves customers sorted by the `created_at` field in descending order.

- **Pagination**:
  ```
  GET /customers?limit=10
  ```
  Returns 10 customers per page.

---

## Author

> **Arendach Taras**  
> arendach.taras@gmail.com

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

