# General Observations

This is an automatically generated documentation based on API calls in the development context. Therefore, some APIs may differ in their input parameters or output responses.

To access the API documentation, go to the [index](/_sidebar) in the side menu.

# Return Patterns

Every **Backend** API returns the following parameters by default:

```json
{
  "result": integer,  // return result
  "state":  1,        // standard connection result
  "header": null,     // Content-Type header return
  "policy": null,     // CORS header return
  "page":   integer,  // current page of listing/pagination
  "data":   ...       // returned data (array boolean or text)
}
```

**result** Parameter:

| **result** | Description |
| ------ | ------ |
| -400 | Missing parameters |
| -401 | Authentication failed/unavailable |
| -403 | No access to this area |
| -404 | Method not found |
| -405 | Method not allowed |
| -406 | Method not accepted in this context |
| -407 | Missing authentication token |
| -408 | Timeout/Execution time expired |
| -409 | Conflict/Method not completed |
| -411 | Invalid parameters |
| -500 | Internal communication error |
| -501 | Method not implemented |
| -502 | Static function cannot be reached |
| -503 | Client database cannot be reached |
| -504 | External resource took too long to respond |
| -505 | Problems with data storage |
| [-2 ... < -9] | Method/API specific errors, useful for identifying cutoff point |
| -1 | Failed to execute action, various reasons |
| 0 | Null return |
| 1 | Successful return |
| [2 ... > 10] | Successful return with the number of results returned |


Other parameters:

| Parameter | Description |
| ------ | ------ |
| state | Default: 1. To identify that the connection was successful with the server/API |
| header | Identifier for applying `header("Content-Type: application/json")`. Typically null or false if not applied |
| policy | Identifier for applying `header("Access-Control-Allow-Origin: *")`. Typically null or false if not applied |
| page | Current page which the API is filtering the results `?page=1` |
| data | Data return parameter |
