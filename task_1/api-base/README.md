#API Base Composer Bundle

This project encloses the common functionalities for most of the Biome projects. In particular, it contains functionalities that provide security and filtering facilities.

It is structured as composer vendor package and it can be used as Symfony bundle,

__Table of Content__

[[_TOC_]]


### Setting up authentication

In order to use this bundle in your project, authentication must be setup. 
  1. To allow access to api-base bundle, you need to create access token in `api-base` package GitLab project. [More info](https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html)
  2. Then use this token in the project where api-base bundle is needed
```
     composer config repositories.git.ufz.de/729 '{"type": "composer", "url": "https://git.ufz.de/api/v4/group/729/-/packages/composer/packages.json"}'
     composer config gitlab-domains git.ufz.de
     composer config gitlab-token.git.ufz.de ACCESS_TOKEN
```
These commands will add repository to your `composer.json` and create a file `auth.json`. With these setup, `composer` commands in your local environment will be able to access and download api-base bundle from UFZ Gitlab package registry.

NOTE: make sure you add `auth.json` to your `.gitignore` file, because you should never store any credentials in your git repo.


### Components


- for more information regarding the structure of this bundle see [Symfony: Best Practices for Reusable Bundles](https://symfony.com/doc/5.3/bundles/best_practices.html)
- below you will find a list of all available components and their tasks


#### Controller 


- for more information see [Creating Custom Operations and Controllers](https://api-platform.com/docs/core/controllers/#creating-custom-operations-and-controllers)


- `Controller/CreateMediaItemAction.php`
   - receive and validate request params for file upload
   - check whether files for the upload are for public or restricted bucket
   - calling the corresponding upload service
   - the respective endpoints are defined in `DTO/MediaItem.php`


- `Controller/DeleteMediaItemAction.php`
   - receive and validate request params for uploaded files deletion
   - check whether files should be deleted in public or restricted bucket
   - check if target paths are supported  
   - calling the corresponding deletion service
   - the respective endpoints are defined in `DTO/MediaItem.php`

#### DependencyInjection


- `DependencyInjection/ApiBaseExtension.php`
   - service for loading service configuration, see also [How to Load Service Configuration inside a Bundle](https://symfony.com/doc/5.3/bundles/extension.html) 

#### Doctrine


- `Doctrine/EventListener/FixPostgreSqlDefaultSchemaListener.php`
   - doctrine PostgreSQL default schema fix for symfony

#### DTO


- `DTO/Filter.php`
   - DTO used for a custom query in combination with `Resolver/FilterApplyResolver.php`  


- `DTO/MediaItem.php`
   - DTO used for file upload (a MediaItem is created from each uploaded file before it is uploaded to s3)

#### Resolver


- `Resolver/FilterApplyResolver.php`
   - used for a custom query in combination with `DTO/Filter.php` see also, [Custom Queries](https://api-platform.com/docs/v2.5/core/graphql/#custom-queries)
   - check for supplied args, call condition compilation via `Service/FilterService.php` for corresponding filter history
   - compile results collection before return as packed ids or usual elements


#### Security

- The security components originally come from the [apiplatform-demo](https://git.ufz.de/rdm/biome/references/apiplatform-demo) application developed by the UFZ.

- For more detailed information see: 
  - [apiplatform-demo: Permissions explained](https://git.ufz.de/rdm/biome/references/apiplatform-demo#permissions-explained)
  - [apiplatform-demo: Default filter and fuzzy search](https://git.ufz.de/rdm/biome/references/apiplatform-demo#default-filter-and-fuzzy-search)
  - [API Platform: Changing the Serialization Context Dynamically](https://api-platform.com/docs/v2.5/core/serialization/#changing-the-serialization-context-dynamically)


- `Security/ContextBuilder/EntityContextBuilderGraphQl.php`
- `Security/ContextBuilder/EntityContextBuilderRest.php`


- `Security/Filter/GenericFieldBasedFilter.php`


- `Security/JWT/JWTEncoder.php`
- `Security/JWT/LcobucciSignerFactory.php`
- `Security/JWT/OidcHttpClient.php`
- `Security/JWT/OidcProvider.php`
- `Security/JWT/PublicAccessAuthenticator.php`
  - authenticator for public access
- `Security/JWT/TokenAuthenticator.php`


- `Security/Permission/AbstractOidcPermissionProvider.php`
- `Security/Permission/AbstractPermissionProvider.php`
- `Security/Permission/EntityPermission.php`
- `Security/QueryExtension/PermissionBasedDataFilter.php`


- `Security/User/AbstractUser.php`
- `Security/User/HumanUser.php`
- `Security/User/MachineUser.php`
- `Security/User/UserFactory.php`
  

- `Security/Utils/EntityUtils.php`
- `Security/Utils/SerialisationGroupsHelper.php`


- `Security/Voter/DeletePermissionForCurrentEntityVoter.php`
- `Security/Voter/UpdatePermissionForCurrentEntityVoter.php`


#### Service


- `Service/Media/ProfilePictures/UserProfilePictureService.php`
   - create or update profile image urls in main project and delete previous images in s3 after profile image upload


- `Service/Media/AbstractUploadService.php`
   - parent class for `Service/Media/PublicUploadService.php` and `Service/Media/RestrictedUploadService.php` 
   - checking for supported file types
   - creating MediaItems from the user's uploaded files with unique file names
   - subdivision into images and documents
   - generates thumbnails for images  
   - calling the respective upload service (`Service/Media/PublicMediaItemUploadService.php` or `Service/Media/RestrictedMediaItemUploadService.php`)


- `Service/Media/BucketDirectoryNamerService.php`
   - responsible for getting/setting upload folder for s3  


- `Service/Media/MediaDeletionService.php`
   - offers the methods for deleting files(for images incl. thumbnails) in s3  


- `Service/Media/PublicMediaItemUploadService.php`
   - contains the method for the file upload which finally calls the upload into the public s3 bucket via the `Vich\UploaderBundle\Handler\UploadHandler`


- `Service/Media/PublicUploadService.php`
   - extends `Service/Media/AbstractUploadService.php`
   - contains upload method via `Service/Media/PublicMediaItemUploadService.php` 
   - collect image, thumbnail and/or document urls supplied from s3 after upload


- `Service/Media/RestrictedMediaItemUploadService.php`
   - contains the method for the file upload which finally calls the upload into the restricted s3 bucket via the `Vich\UploaderBundle\Handler\UploadHandler`


- `Service/Media/RestrictedUploadService.php`
   - extends `Service/Media/AbstractUploadService.php`
   - trigger upload via `Service/Media/RestrictedMediaItemUploadService.php`
   - persist the image urls in main project via method call to `UploadImagePersisterInterface` implementation
   - collect image, thumbnail and/or document urls(pre signed) supplied from s3 after upload 


- `Service/Media/SupportedFilesService.php`
   - check whether a file is supported by its file ending  


- `Service/Media/ThumbnailNamingService.php`
   - determine the thumbnail name based on the original file name  


- `Service/Media/ThumbnailService.php`
   - generate thumbnails for an original file  


- `Service/Media/UrlSignationService.php`
   - creating pre signed urls 


- `Service/FilterService.php`
   - service that provides filter applying and combining facilities


#### Validator


- `Validator/FilterCombinationValidator.php`
   - regular validating logic for filter combination expressions  


- `Validator/ValidFilterExpression.php`
   - validation constraint for `Validator/ValidFilterExpressionValidator.php`  


- `Validator/ValidFilterExpressionValidator.php`
   - filter history filter expression validator


#### Other


- `ApiBaseBundle.php`
   - configure the bundle path to its root directory, see also: [Best Practices for Reusable Bundles/Directory Structure](https://symfony.com/doc/5.3/bundles/best_practices.html#directory-structure)



### Including `api-base` bundle in your project

  1. Add dependency to your `composer.json`:
```
     "require": {
        ...
        "ufz/api-base": "~1.0"
    }
```
  2. Add repositories to `composer.json`
```
"repositories": [{
    "type": "composer",
    "url": "https://git.ufz.de/api/v4/group/729/-/packages/composer/packages.json"
  }],
```
  3. Add domain to `composer.json`
```
"config": {
   ...
    "gitlab-domains": ["git.ufz.de"]
  },
```
  4. Run `composer update ufz/api-base`
  5. In the file `config/bundles.php` add api-base bundle:
```
return [
    ...
    Ufz\ApiBase\ApiBaseBundle::class => ['all' => true],
```
  4. In the file `config/api_platform.yaml` add filtering paths. Unfortunately, it is not yet possible to use '@ApiBaseBundle/DTO' annotation yet, but absolute path must be used:
```
mapping :
    paths : [ '%kernel.project_dir%/src/Entity', '%kernel.project_dir%/vendor/ufz/api-base/src/DTO' ]
```
  5. Define security encoder (this could be an example of how to use security from api-base):
```
    config/packages/security.yaml:
    guard:
        authenticators:
          - Ufz\ApiBase\Security\JWT\TokenAuthenticator
          
    config/packages/lexik_jwt_authentication.yaml:
    encoder:
        service: Ufz\ApiBase\Security\JWT\JWTEncoder
```
  6. Inject dependencies by autowiring - by adding namespace to `services.yaml` that would autowire all classes in the respective namespace:
```
   services:
     ...
     Ufz\ApiBase\:
     resource: '@ApiBaseBundle/*'
     exclude: '@ApiBaseBundle/{DependencyInjection,Entity,Migrations,Tests,Kernel.php}'
```

  7. Or specify required dependencies manually, for example like this:
```
  services:
    ...

    Ufz\ApiBase\Validator\ValidFilterExpressionValidator:
      tags: ['validator.constraint_validator']
    
    Ufz\ApiBase\Validator\FilterCombinationValidator:
      class: Ufz\ApiBase\Validator\FilterCombinationValidator
    
    api_filter_history_repository:
      class : Doctrine\ORM\EntityRepository
      factory : [ "@doctrine.orm.entity_manager", getRepository ]
      arguments :
        - App\Entity\FilterHistory
    
    api_filter_service:
      class: Ufz\ApiBase\Service\FilterService
      arguments:
        - '@Ufz\ApiBase\Validator\FilterCombinationValidator'
        - '@doctrine'
        - '@Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter'
        - '@serializer'
        - '@api_filter_history_repository'
        - '@security.helper'
    
    Ufz\ApiBase\Resolver\FilterApplyResolver:
     arguments:
      - '@api_filter_service'
      - '%api_platform.collection.pagination.items_per_page%'
```

### Integrating into GitLab CI

1. Define the variable UFZ_COMPOSER_ACCESS_TOKEN in your main GitLab project:
   1. Open the project and click Settings > CI/CD
   2. Add variable UFZ_COMPOSER_ACCESS_TOKEN in the section `Variables` with the value (access token) from the page https://confluence.digitalearth-hgf.de/pages/viewpage.action?pageId=16722584

2. Add the following comand to your `.gitlab-ci.yml` file, under `build > script`:
```
build:
  ...
  script:
    ...
    - php ../bin/composer.phar config gitlab-token.git.ufz.de ${UFZ_COMPOSER_ACCESS_TOKEN}
```

### Using filtering functionalities

Two interfaces are provided in the bundle, that are meant to be used to define FilterHistory entity and FilterHistory repository. It is the responsibility of the main project to handle persistence and FilterHistory entity, as it may happen that these can slightly differ for specific projects. 
  1. FilterHistory entity must implement interface `FilterHistoryEntityInterface`. Method `getClassNameFromType()` should provide some sort of mapping between FilterHistory type and corresponding class name from the domain model in the main project. Example from TMD project: type `observation` maps to `App\Entity\Observation` class.
  2. Repository that handles FilterHistory entity in the main project must implement interface `FilterHistoryRepositoryInterface` and just make sure it returns its query builder in the method `getFilterHistoryQueryBuilder()`
  3. Entities should use following imports in order to use filtering and security annotations and functions:
```
    use Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter;
    use Ufz\ApiBase\Security\Interfaces\AuthorizationRequiredEntityInterface;
```

### Implementing external filter in main project

There might be a need to implement additional filtering functionalities in main project, for example: TMD project has integration with Taxonomy API and there is a need to be able to additionaly filter entities by taxonomy data. This is a special case, as filtering needs to happen in 2 steps: querying Taxonomy API and translating results into criteria based on species IDs. For this, an "external" filter TaxonomyFilter is created in TMD. This filter needs to implement interface `ExternalFilterInterface` from `api-base` in order to have it integrated into standard filtering chain.

These are the steps to implement new external filter:
1. Implement `Ufz\ApiBase\Interfaces\ExternalFilterInterface` in the filter implementation class in main project
2. Add the following (replace class name to match the FCQN of your filter class) to services.yaml, to inject the filter into GenericFieldBasedFilter in api-base:
```
      Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter:
        calls:
          - addExternalFilter: ['@App\Filter\TaxonomyFilter']
```

### Common Vulnerabilities and Exposures (CVE)

#### GitLab

Checking the using composer packages for known vulnerabilities and exposures is done in the ci test stage(see [.gitlab-ci.yml](.gitlab-ci.yml)).  
Currently, [cs278/composer-audit](https://packagist.org/packages/cs278/composer-audit) is used for auditing, with the possibility to define additional options.

If you decide the risk of detected vce is acceptable or not applicable and you cannot otherwise upgrade the package to resolve the problem, you can have it ignored during the audit,
but it must be documented in detail here.

#### Local

If you have already installed the [Symfony CLI](https://symfony.com/download), the check can also be done locally with `symfony check:security`.


## S3 Bucket handling

The s3 file upload is handled differently in terms of public/restricted bucket, as well as user entity and other entities.

### Public Bucket

Process of uploading different entities(simplified)

__Profile image for user entity__  
(single file)

1. original image file comes via REST request
2. thumbnails (medium + small) are generated
3. original + thumbnails are uploaded to s3 and urls are returned from s3
4. urls returned by s3 are checked and updated in the db or saved for the first time

__Files for other entities (e.g. NewsItem)__  
(multiple files - images and/or documents)

Images

1. original image file(s) is received via REST request
2. thumbnails (medium + small) are generated
3. original + thumbnails are uploaded to s3 and urls are returned from s3
4. urls returned by s3 are returned as array in json response 

Documents

1. the original file(s) is received via REST request
2. files are uploaded to s3 and urls are returned from s3
3. urls returned by s3 are returned as array in json response 

### Restricted bucket
(multiple files - images only)

1. original image files is received via REST request
2. thumbnails (medium + small) are generated
3. original + thumbnails are uploaded to s3
4. original + thumbnails are stored in db via image ref and thumbnail ref objects
5. image ref + thumbnail ref objects are returned as array incl. presigned urls


__In order to include these services for handling image uploads in AWS S3, following is required:__


  1. Implement interface `UploadImagePersisterInterface`

  In the main project there must be a class that is responsible for persisting image and thumbnail metadata in the project database (example in TMD: ImageRef and ThumbnailRef). This class must implement interface `Ufz\ApiBase\Interfaces\UploadImagePersisterInterface` and it will be auto-wired when used inside of api-base package. Please note that this is not API Platform image persister. Example from TMD:
```
<?php

namespace App\DataPersister;

use App\Entity\ImageRef;
use App\Entity\ThumbnailRef;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;

class ImagePersister implements UploadImagePersisterInterface
{
    private EntityManagerInterface $entityManager;

    private UserService $userService;

    public function __construct(EntityManagerInterface $entityManager, UserService $userService)
    {
        $this->entityManager = $entityManager;
        $this->userService = $userService;
    }

    public function persistImage(string $ref, $size)
    {
        $imageRef = (new ImageRef())
            ->setRef($ref)
            ->setHeight($size->getHeight())
            ->setWidth($size->getWidth())
            ->setCreatedBy($this->userService->getLoggedUser())
            ->setCreatedAt(new \DateTime());

        $imageRef
            ->setLastModifiedBy($imageRef->getCreatedBy())
            ->setModifiedAt($imageRef->getCreatedAt());

        $this->entityManager->persist($imageRef);
        return $imageRef;
    }

    public function persistThumbnail(string $ref, string $label, string $signedUrl, $size, $imageReference)
    {
        $thumbnailRef = new ThumbnailRef();
        $thumbnailRef->setImageRef($imageReference);
        $thumbnailRef->setRef($ref);
        $thumbnailRef->setLabel($label);
        $thumbnailRef->setHeight($size->getHeight());
        $thumbnailRef->setWidth($size->getWidth());
        $thumbnailRef->setUrl($signedUrl);
        $this->entityManager->persist($thumbnailRef);
        return $thumbnailRef;
    }
}

```

  2. Implement interface `ProfilePictureUserInterface`

  Implement this interface in the User entity of the main project. It is required for handling urls of profile images.

  4. Define services in service container

  Dependencies like `vichy uploader` and `flysystem` are configured from the top project and therefore, services do not rely on auto-wiring, but need to be injected manually. Here is an example from TMD project how these services can be specified:

```
### S3 handling
  public_supported_files_service:
    class: Ufz\ApiBase\Service\Media\SupportedFilesService
    arguments: [ [ 'jpg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar' ] ]

  restricted_supported_files_service:
    class: Ufz\ApiBase\Service\Media\SupportedFilesService
    arguments: [ [ 'jpg', 'png' ] ]

  Ufz\ApiBase\Service\Media\SupportedFilesInterface $publicSupportedFiles: '@public_supported_files_service'
  Ufz\ApiBase\Service\Media\SupportedFilesInterface $restrictedSupportedFiles: '@restricted_supported_files_service'

  # MediaItemUpload means just uploading a single media item to a storage
  public_media_item_upload:
    class: Ufz\ApiBase\Service\Media\PublicMediaItemUploadService

  restricted_media_item_upload:
    class: Ufz\ApiBase\Service\Media\RestrictedMediaItemUploadService

  Ufz\ApiBase\Service\Media\MediaItemUploadInterface $publicMediaItemUpload: '@public_media_item_upload'
  Ufz\ApiBase\Service\Media\MediaItemUploadInterface $restrictedMediaItemUpload: '@restricted_media_item_upload'

  # UploadServices upload an array of files, generate thumbnails and verify the files
  public_upload_service:
    class: Ufz\ApiBase\Service\Media\PublicUploadService

  restricted_upload_service:
    class: Ufz\ApiBase\Service\Media\RestrictedUploadService

  is_media_deletable_service:
    class: Ufz\ApiBase\Service\Media\IsMediaDeletableService

  Ufz\ApiBase\Service\Media\IsMediaDeletableInterface $isMediaDeletableInterface: '@is_media_deletable_interface'

  public_media_deletion_service:
    class: Ufz\ApiBase\Service\Media\MediaDeletionService
    arguments:
      $bucket: '%env(resolve:S3_PUBLIC_BUCKET_NAME)%'

  Ufz\ApiBase\Service\Media\MediaDeletionInterface $publicMediaDeletionService: '@public_media_deletion_service'

  Ufz\ApiBase\Service\Media\AbstractUploadService $publicUploadService: '@public_upload_service'
  Ufz\ApiBase\Service\Media\AbstractUploadService $restrictedUploadService: '@restricted_upload_service'

  Imagine\Image\ImagineInterface:
    # use either Imagine\Gd\Imagine, Imagine\Imagick\Imagine or Imagine\Gmagick\Imagine
    # depending on the installed image processing library
    class: Imagine\Gd\Imagine

  Ufz\ApiBase\Service\Media\ThumbnailService:
    arguments:
      $suffixSeparator: '%thumbnail.suffix.separator%'
      $sizes: '%thumbnail.sizes%'

  Ufz\ApiBase\Service\Media\ThumbnailNamingService:
    arguments:
      $suffixSeparator: '%thumbnail.suffix.separator%'
      $sizes: '%thumbnail.sizes%'

  Ufz\ApiBase\Service\Media\UrlSignationInterface:
    class: Ufz\ApiBase\Service\Media\UrlSignationService
    arguments:
      $bucket: '%env(resolve:S3_RESTRICTED_BUCKET_NAME)%'
      $expires: '+%env(resolve:S3_RESTRICTED_BUCKET_URL_VALID_MINUTES)% minutes'

  Ufz\ApiBase\Service\Media\BucketDirectoryNamerService :
    public : true

  user_profile_picture_service :
    class : Ufz\ApiBase\Service\Media\ProfilePictures\UserProfilePictureService

  Ufz\ApiBase\Service\Media\UserProfilePictureInterface $profilePictures : '@user_profile_picture_service'

```

#### S3 Bucket handling - ActiveScale

Some S3‑compatible storages (e.g. ActiveScale) reject presigned URLs that include the query parameter X-Amz-Content-Sha256.
The AWS SDK for PHP adds this header during presign for S3 and then mirrors it into the querystring, which triggers a
501/NotImplemented error on such backends.
This feature provides a custom S3 signer that prevents X-Amz-Content-Sha256 from being added during presign,
so it never appears as a query parameter. It is compatible with AWS S3 and S3‑compatible targets.

- `Ufz\ApiBase\Aws\Signature\CustomS3SignatureV4`
    - Extends AWS `S3SignatureV4` and overrides `presign()` to call the generic `SignatureV4::presign()` (no `X-Amz-Content-Sha256` injection). Preserves `x-amz-region-set: *` for global accesspoint special case.
- `Ufz\ApiBase\Aws\Signature\CustomSignatureProvider`
    - A callable signature provider returning `CustomS3SignatureV4` for `service === 's3'` when enabled, otherwise delegating to the default AWS signature provider.
- Bundle config key: `api_base.aws_s3.disable_content_sha256_in_presign` (boolean, default false)

##### Enable in your Symfony app

- Register the bundle (in the consuming app):
  - `config/bundles.php` contains: `Ufz\ApiBase\ApiBaseBundle::class => ['all' => true]`
- Wire the Api‑Base provider to your S3 client:
```yaml
# config/packages/prod/services.yaml (similar for dev/test)
aws.s3_client:
  class: Aws\S3\S3Client
  arguments:
    - version: '%s3_version%'
      # ...
      signature_provider: '@Ufz\ApiBase\Aws\Signature\CustomSignatureProvider'
```
- Toggle via environment variable (recommended)
  - Add a default parameter and map the bundle option to an ENV with safe fallback:
```yaml
# config/services.yaml
parameters:
  app.flag.default_false: 'false'
```
```yml
# config/packages/api_base.yaml
api_base:
  aws_s3:
    disable_content_sha256_in_presign: '%env(bool:default:app.flag.default_false:API_BASE_AWS_S3_DISABLE_CONTENT_SHA256_IN_PRESIGN)%'
```

- Important for DI in consuming apps If your app auto‑registers Api‑Base classes as services, exclude the signer path to avoid overriding the bundle’s service definition (which injects the flag):
```yaml
# config/services.yaml
Ufz\ApiBase\:
  resource: '@ApiBaseBundle/*'
  exclude:
    - '@ApiBaseBundle/{DependencyInjection,Entity,Migrations,Tests,Kernel.php}'
    - '@ApiBaseBundle/Aws/Signature'
```
- Enable in environments that need it:
```yaml
# docker-compose.yml
services: 
  tmd-backend-php-fpm:
    environment:
      API_BASE_AWS_S3_DISABLE_CONTENT_SHA256_IN_PRESIGN: 'true'
```

### Notes

- TMD project: in GitLab CI/CD -> Variables, a new variable is created to store the access token for api-base package registry, caled `UFZ_COMPOSER_ACCESS_TOKEN`. This is required for downloading packages in CI pipeline (unfortunately, authentication by CI_JOB_TOKEN is not possible).
