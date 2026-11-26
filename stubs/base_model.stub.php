<?php
/** @generate-class-entries */

namespace Attributes\Strictify {
   abstract class BaseModel {
      protected function beforeValidation(array $rawData, ModelConfigs $configs): array {}
      protected function afterValidation(array $rawData, ModelConfigs $configs): void {}
      public function toDict(bool $includeSensitive = false, bool $useAlias = false): array {}
   }
}
