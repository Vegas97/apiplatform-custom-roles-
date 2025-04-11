# API Platform Custom Roles Flow

This document explains the flow of a request through the API Platform custom roles implementation, from the initial request to the final response.

## Overview

The system implements a custom role-based access control mechanism for API Platform resources. It allows different portals (admin, workspace, distributor) to access different fields of the same resource based on their roles.

## Request Flow Diagram

```
Request → JWT Authentication → UserDtoProvider → FieldAccessResolver → AllowedRoles → Filtered Response
```

## Detailed Flow

### 1. Initial Request

A client sends a request to the API with a JWT token in the Authorization header:

```
GET /api/user_dtos/1
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

The JWT token contains:
- User ID
- Roles (e.g., `ROLE_SYSTEMBFF-USERDTO_ACCESS`)
- Portal (e.g., "distributor", "admin", "workspace")

### 2. JWT Authentication (`src/Service/JwtService.php`)

- `JwtService::extractAuthData()` extracts the JWT token from the Authorization header
- `JwtService::extractAndDecodeToken()` decodes the token using the configured secret key
- Returns the portal and roles from the token
- In test environments, can also use query parameters as a fallback

### 3. Data Provider (`src/State/UserDtoProvider.php`)

- API Platform routes the request to `UserDtoProvider::provide()`
- The provider extracts authentication data using the JWT service
- Retrieves the requested resource (in this case, a user with ID "1")
- Calls `FieldAccessResolver` to determine which fields are accessible

### 4. Field Access Resolution (`src/Service/FieldAccessResolver.php`)

- `FieldAccessResolver::getAccessibleFields()` analyzes the `UserDto` class
- Uses reflection to examine each property and its `AllowedRoles` attribute
- For each property, determines if the current user has access based on:
  - The user's roles
  - The current portal
  - The BFF name (from configuration)
  - The entity name

### 5. Role-Based Access Control (`src/Attribute/AllowedRoles.php`)

- `AllowedRoles::hasAccess()` checks if the user has the required role for each field
- The `AllowedRoles` attribute on each property defines which portals can access it and with what roles
- Example from `UserDto.php`:
  ```php
  #[AllowedRoles([
      'admin' => ['ACCESS'],
      'workspace' => ['ACCESS'],
      'distributor' => ['ACCESS']
  ])]
  public string $username = '';
  ```

### 6. Field Filtering (`src/State/UserDtoProvider.php`)

- `UserDtoProvider::filterUserFields()` creates a new `UserDto` with only the accessible fields
- If a field is not accessible, it's excluded from the response
- If no fields are accessible, returns null (no access)

### 7. Response Generation

- The filtered `UserDto` is returned to API Platform
- API Platform serializes it to JSON-LD format
- The response only includes the fields the user has access to

## Example

For a request with portal "distributor" and role "ROLE_SYSTEMBFF-USERDTO_ACCESS":

1. `id` and `username` fields are accessible (distributor has 'ACCESS' permission)
2. `email` field is not accessible (distributor has empty permission list)
3. `birthDate` field is not accessible (distributor is not listed)

The response will only include:
```json
{
  "@context": "/api/contexts/UserDto",
  "@id": "/api/user_dtos/1",
  "@type": "UserDto",
  "id": "1",
  "username": "john_doe"
}
```

## Testing

The flow can be tested using `tests/Api/UserDtoTest.php`:

- `testGetCollection()`: Tests the collection endpoint
- `testGetItemWithPortalAndRoles()`: Tests the item endpoint with different portals and roles

To run the tests:
```
APP_ENV=test bin/phpunit tests/Api/UserDtoTest.php
```
