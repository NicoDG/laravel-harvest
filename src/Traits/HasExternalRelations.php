<?php

namespace Byte5\LaravelHarvest\Traits;

use \Illuminate\Support\Str;

trait HasExternalRelations
{
    /**
     * @return array
     */
    abstract protected function getExternalRelations() : array;

    /**
     * Loads relations from harvest api external relationships.
     *
     * @param  array|string $relations
     * @param bool $save
     * @return $this
     */
    public function loadExternal($relations = '*', $save = true)
    {
        // normalize input
        if ($relations === '*') {
            $relations = $this->getExternalRelations();
        }

        if (is_string($relations)) {
            $relations = [$relations];
        }

        $relations = $this->filterRelations($relations);

        $this->mapRelations($relations, $save);

        return $this;
    }

    /**
     * Only return relevant relations.
     *
     * @param $relations
     * @return Illuminate\Support\Collection
     */
    private function filterRelations($relations)
    {
        return collect($relations)->filter(function ($relation) {
            return $this->relationExists($relation)
                && $this->externalRelationIdExists($relation)
                && $this->relationHasNotBeenEstablished($relation);
        });
    }

    /**
     * Checks if the relation does exist in the external relations array.
     *
     * @param $relation
     * @return bool
     */
    private function relationExists($relation)
    {
        return in_array($relation, $this->getExternalRelations()) || array_has($this->getExternalRelations(), $relation);
    }

    /**
     * Checks if the external relation id exists.
     *
     * @param $relation
     * @return bool
     */
    private function externalRelationIdExists($relation)
    {
        return $this->{'external_'.Str::snake($relation).'_id'} != null;
    }

    /**
     * Checks if the relation has already been established.
     *
     * @param $relation
     * @return bool
     */
    private function relationHasNotBeenEstablished($relation)
    {
        return ! $this->{$relation} || $this->{Str::snake($relation).'_id'} == null;
    }

    /**
     * Maps given relations with their models.
     *
     * @param $relations
     * @param $save
     */
    private function mapRelations($relations, $save)
    {
        $relations->each(function ($relation) use ($save) {
            $relationId = $this->{'external_'.Str::snake($relation).'_id'};
            $relationKey = $this->getRelationKey($relation);

            if ($existingModel = $this->checkForLocalExistence($relationKey, $relationId)) {
                return $this->$relationKey()->associate($existingModel);
            }

            $relationModel = call_user_func('Harvest::'.$relationKey)
                                ->find($relationId)
                                ->toCollection()
                                ->first();

            if ($save && config('harvest.uses_database')) {
                $relationModel->save();
            }

            $this->$relationKey()->associate($relationModel);
        });
    }

    /**
     * Checks for the local existence of the model via external_id.
     *
     * @param $modelKey
     * @param $id
     * @return Model
     */
    private function checkForLocalExistence($modelKey, $id)
    {
        if (! config('harvest.uses_database')) {
            return;
        }

        $modelMethod = '\Byte5\LaravelHarvest\Models\\'.ucfirst(camel_case($modelKey)).'::whereExternalId';

        return call_user_func($modelMethod, $id)->first();
    }

    /**
     * Returns the key of the passed in relation.
     *
     * @param $relation
     * @return string
     */
    private function getRelationKey($relation)
    {
        return in_array($relation, $this->getExternalRelations())
            ? $relation : $this->getExternalRelations()[$relation];
    }
}
