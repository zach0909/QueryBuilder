<?php

class Builder
{
    private string $tableName;
    private array $currentTableData = [];
    private array $selectFields = [];
    public function table (string $table): static
    {
        self::verifyTable($table);
        $this->tableName = $table;
        $this->fetchCurrentTable();
        return $this;
    }

    //Throws exception if table not found in db
    private static function verifyTable ($table): void 
    {
        $tablesInDb = scandir("./tables");
        foreach ($tablesInDb as $tableInDb) {
            if ($tableInDb == "$table.json") {
                return;
            }
        }
        
        //if we got here, it means that the table was not found. Throw exception.
        // throw ErrorException ("Table $table not found in database");
    }

    //used to get and set all contents from current table into class property
    private function fetchCurrentTable (): void
    {
        $path = "./tables/" . $this->tableName . ".json";
        $contents = file_get_contents($path);
        $data = json_decode($contents, true);
        $this->currentTableData = $data;
    }

    public function select (array $fields): static
    {
        $this->selectFields = $fields;
        return $this;
    }
    
    /**
     * @param string $key
     * @param string $operator
     * @param mixed $value
     * @return Builder
     */
    public function where (string $key, string $operator, mixed $value): static
    {
        if ($operator == "=") {
            $this->getRecordsByMatch($key, $value);
        } else {
            $this->getRecordsByComparison($key, $operator, $value);
        }
        return $this;
    }

    private function getRecordsByComparison (string $column, string $operator, mixed $value): void
    {
        $fetchedRecords = [];
        foreach ($this->currentTableData as $index => $record) {
            $comparison = $operator == '>' ? $record[$column] > $value : $record[$column] < $value;
            if ($comparison) {
                $fetchedRecords[$index] = $record;
            }
        }
        $this->currentTableData = $fetchedRecords;
    }

    private function getRecordsByMatch (string $column, mixed $value): void
    {
        $fetchedRecords = [];
        foreach ($this->currentTableData as $index => $record) {
            if ($record[$column] == $value) {
                $fetchedRecords[$index] = $record;
            }
        }
        $this->currentTableData = $fetchedRecords;
    }
    
    public function get (): array  
    {
        $allRecords = array_values($this->currentTableData);
    
        if ($this->selectFields) {
            $dataToReturn = [];
            foreach ($allRecords as $record) {
                $newRecord = [];
                foreach ($this->selectFields as $field) {
                    $newRecord[$field] = $record[$field];
                }
                $dataToReturn[] = $newRecord;
            }
            return $dataToReturn;
        }
        return $allRecords;
    }

    //for the sake of this assignment, the assumption is that tables are not empty and that every record already in the table
    //is structured correctly. To handle an empty table, a system would need to be put in to validate the data being passed in
    //to this method.

    //also, the assumption is that no fields are required, because otherwise there would have to be a check for that.
    public function insert (array $data): bool|int
    {
        $columns = array_keys($this->currentTableData[0]);
         
        $newRecord["id"] = $this->generateNextIdForInsert();

        foreach ($data as $column => $value) {
            if (in_array($column, $columns)) {
                $newRecord[$column] = $value;
            }
        }

        $this->currentTableData[] = $newRecord;        
        
        return $this->save();
    }

    public function delete(): array
    {
        $indexesToDelete = array_keys($this->currentTableData);
        $this->fetchCurrentTable();

        foreach ($indexesToDelete as $index) {
            unset($this->currentTableData[$index]);
        }        
        
        $success = $this->save();
        return ["success" => !!$success, "countOfRecordsDeleted" => count($indexesToDelete)];
    }

    public function update(array $data): array
    {
        $indexesToUpdate = array_keys($this->currentTableData);
        $this->fetchCurrentTable();
        //ignore any key in $data that does not belong in the table and only use the columns already there
        foreach ($indexesToUpdate as $index) {
            $currentRecord = $this->currentTableData[$index];
            $fields = array_keys($currentRecord);
            foreach ($data as $key => $value) {
                if (in_array($key, $fields)) {
                    $this->currentTableData[$index][$key] = $value;
                }
            }
        }
        
        $success = $this->save();
        return ["success" => !!$success, "countOfRecordsUpdated" => count($indexesToUpdate)];
    }

    private function save (): bool|int 
    {
        return file_put_contents("./tables/" . $this->tableName . ".json", json_encode($this->currentTableData));
    }

    private function generateNextIdForInsert (): int
    {
        $highestId = 0;
        foreach ($this->currentTableData as $record) {
            if ($record["id"] > $highestId) {
                $highestId = $record["id"];
            }
        }
        return $highestId + 1;
    }

}



/**
 * Examples of how to use the builder:
 */

//example of "select" functionality with specific columns
$builder = new Builder();
$results = $builder->table("products")
    ->select(["id", "name"])
    ->where("id", "<", 3)
    ->get();

print_r($results);
echo "<br><br>";

//example of "select" with multiple "where" clauses
$results2 = $builder->table("users")
    ->where("id", ">", 1)
    ->where("age", ">", 20)
    ->get();
    
print_r($results2);
echo "<br><br>";

//example of "delete". If no "where" clause added, then all contents of the table will be deleted
// $builder->table("users")
//     ->delete();

$result3 = $builder->table("users")->get();
print_r($result3);    
echo "<br><br>";

//example of "update"
$results4 = $builder->table("users")
                ->where('id', ">", 1)
                ->update(["age" => 5000, "color" => "red"]);

print_r($results4);                
echo "<br><br>";

//example of "insert"
$results5 = $builder->table("users")
                ->insert(["name" => "Jone", "age" => 50]);

print_r($builder->table("users")->get());




