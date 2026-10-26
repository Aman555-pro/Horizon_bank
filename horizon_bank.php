<?php
// Database Connection
$connection = new mysqli("localhost", "root", "root");
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error . "\n");
}
echo "MySQL connected successfully.\n";
// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS horizon_bank";
if (!$connection->query($sql)) {
    die("Database error: " . $connection->error . "\n");
}
$connection->select_db("horizon_bank");
// Create Customer Table
$sql = "CREATE TABLE IF NOT EXISTS Customer (
    Customer_ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(50),
    Age INT,
    Gender VARCHAR(10),
    Contact VARCHAR(15),
    Email VARCHAR(100),
    Address VARCHAR(100)
)";
if (!$connection->query($sql)) {
    die("Customer table error: " . $connection->error . "\n");
}
// Create Employee Table
$sql = "CREATE TABLE IF NOT EXISTS Employee (
    Employee_ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(50),
    Designation VARCHAR(50),
    Contact VARCHAR(15),
    Salary DECIMAL(10,2)
)";
if (!$connection->query($sql)) {
    die("Employee table error: " . $connection->error . "\n");
}
// Create Bank Table
$sql = "CREATE TABLE IF NOT EXISTS Bank (
    Bank_ID INT PRIMARY KEY AUTO_INCREMENT,
    Bank_Name VARCHAR(100),
    Branch VARCHAR(100),
    IFSC_Code VARCHAR(20)
)";
if (!$connection->query($sql)) {
    die("Bank table error: " . $connection->error . "\n");
}
// Create Transactions Table
$sql = "CREATE TABLE IF NOT EXISTS Transactions (
    Transaction_ID INT PRIMARY KEY AUTO_INCREMENT,
    Customer_ID INT,
    Bank_ID INT,
    Transaction_Type VARCHAR(10),
    Amount DECIMAL(10,2),
    Transaction_Date DATE,
    FOREIGN KEY (Customer_ID) REFERENCES Customer(Customer_ID),
    FOREIGN KEY (Bank_ID) REFERENCES Bank(Bank_ID)
)";
if (!$connection->query($sql)) {
    die("Transactions table error: " . $connection->error . "\n");
}
// Check Transactions Table Structure
$result = $connection->query("SHOW COLUMNS FROM Transactions");
$columns = [];
while ($column = $result->fetch_assoc()) {
    $columns[] = $column["Field"];
}
if (!in_array("Customer_ID", $columns)) {
    die(
        "ERROR: Customer_ID column is missing from Transactions.\n" .
        "Check your table using DESCRIBE Transactions.\n"
    );
}
echo "All tables checked successfully.\n";
// Main Menu
echo "\n===== HORIZON BANK =====\n";
echo "1. Add Customer\n";
echo "2. Show All Customers\n";
echo "3. Search Customer and Balance\n";
echo "4. Credit Money\n";
echo "5. Debit Money\n";
echo "6. Show All Transactions\n";
echo "7. Update Customer\n";
echo "8. Delete Customer\n";
echo "9. Exit\n";
$choice = (int) readline("Enter your choice: ");
// Option 1: Add Customer
if ($choice == 1) {
    $name = readline("Enter Name: ");
    $age = (int) readline("Enter Age: ");
    $gender = readline("Enter Gender: ");
    $contact = readline("Enter Contact: ");
    $email = readline("Enter Email: ");
    $address = readline("Enter Address: ");
    $sql = "INSERT INTO Customer
            (Name, Age, Gender, Contact, Email, Address)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param(
        "sissss",
        $name,
        $age,
        $gender,
        $contact,
        $email,
        $address
    );
    if ($stmt->execute()) {
        echo "Customer added successfully.\n";
        echo "Customer ID: " . $connection->insert_id . "\n";
    } else {
        echo "Customer insert error: " . $stmt->error . "\n";
    }
    $stmt->close();
}
// Option 2: Show All Customers
elseif ($choice == 2) {
    $sql = "SELECT * FROM Customer";
    $result = $connection->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "\nCustomer ID: " . $row["Customer_ID"] . "\n";
            echo "Name: " . $row["Name"] . "\n";
            echo "Age: " . $row["Age"] . "\n";
            echo "Gender: " . $row["Gender"] . "\n";
            echo "Contact: " . $row["Contact"] . "\n";
            echo "Email: " . $row["Email"] . "\n";
            echo "Address: " . $row["Address"] . "\n";
        }
    } else {
        echo "No customers found.\n";
    }
}
// Option 3: Search Customer and Balance
elseif ($choice == 3) {
    $customerId = (int) readline("Enter Customer ID: ");
    $sql = "SELECT * FROM Customer WHERE Customer_ID = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "\n===== CUSTOMER DETAILS =====\n";
        echo "Customer ID: " . $row["Customer_ID"] . "\n";
        echo "Name: " . $row["Name"] . "\n";
        echo "Age: " . $row["Age"] . "\n";
        echo "Gender: " . $row["Gender"] . "\n";
        echo "Contact: " . $row["Contact"] . "\n";
        echo "Email: " . $row["Email"] . "\n";
        echo "Address: " . $row["Address"] . "\n";
        $balanceSql = "SELECT COALESCE(SUM(
            CASE
                WHEN Transaction_Type = 'CREDIT' THEN Amount
                WHEN Transaction_Type = 'DEBIT' THEN -Amount
                ELSE 0
            END
        ), 0) AS Balance
        FROM Transactions
        WHERE Customer_ID = ?";
        $balanceStmt = $connection->prepare($balanceSql);
        $balanceStmt->bind_param("i", $customerId);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $balanceRow = $balanceResult->fetch_assoc();
        echo "Balance: Rs. " . $balanceRow["Balance"] . "\n";
        $balanceStmt->close();
    } else {
        echo "Customer ID does not exist.\n";
    }
    $stmt->close();
}
//  4: Credit Money
elseif ($choice == 4) {
    $customerId = (int) readline("Enter Customer ID: ");
    $amount = (float) readline("Enter Credit Amount: ");
    if ($amount <= 0) {
        echo "Amount must be greater than zero.\n";
    } else {
        $check = $connection->prepare(
            "SELECT Customer_ID FROM Customer WHERE Customer_ID = ?"
        );
        $check->bind_param("i", $customerId);
        $check->execute();
         $customerResult = $check->get_result();
        if ($customerResult->num_rows == 0) {
             echo "Customer does not exist.\n";
        } else {
             $bankResult = $connection->query(
                "SELECT Bank_ID FROM Bank ORDER BY Bank_ID LIMIT 1"
            );
            if ($bankResult->num_rows == 0) {
                echo "Please add a Bank record first.\n";
            } else {
                $bankRow = $bankResult->fetch_assoc();
                $bankId = (int) $bankRow["Bank_ID"];
                $type = "CREDIT";
                $sql = "INSERT INTO Transactions
                    (Customer_ID, Bank_ID, Transaction_Type,
                     Amount, Transaction_Date)
                    VALUES (?, ?, ?, ?, CURDATE())";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param(
                    "iisd",
                    $customerId,
                    $bankId,
                    $type,
                    $amount
                );
                if ($stmt->execute()) {
                    echo "Money credited successfully.\n";
                } else {
                    echo "Credit error: " . $stmt->error . "\n";
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}
// 5: Debit Money
elseif ($choice == 5) {
    $customerId = (int) readline("Enter Customer ID: ");
    $amount = (float) readline("Enter Debit Amount: ");
    if ($amount <= 0) {
        echo "Amount must be greater than zero.\n";
    } else {
        $check = $connection->prepare(
            "SELECT Customer_ID FROM Customer WHERE Customer_ID = ?"
        );
        $check->bind_param("i", $customerId);
        $check->execute();
        $customerResult = $check->get_result();
        if ($customerResult->num_rows == 0) {
            echo "Customer does not exist.\n";
        } else {
            $balanceSql = "SELECT COALESCE(SUM(
                CASE
                    WHEN Transaction_Type = 'CREDIT' THEN Amount
                    WHEN Transaction_Type = 'DEBIT' THEN -Amount
                    ELSE 0
                END
            ), 0) AS Balance
            FROM Transactions
            WHERE Customer_ID = ?";
            $balanceStmt = $connection->prepare($balanceSql);
            $balanceStmt->bind_param("i", $customerId);
            $balanceStmt->execute();
            $balanceResult = $balanceStmt->get_result();
            $balanceRow = $balanceResult->fetch_assoc();
            $balance = (float) $balanceRow["Balance"];
            $balanceStmt->close();
            if ($amount > $balance) {
                echo "Insufficient balance. Current balance: Rs. $balance\n";
            } else {
                $bankResult = $connection->query(
                    "SELECT Bank_ID FROM Bank ORDER BY Bank_ID LIMIT 1"
                );
                if ($bankResult->num_rows == 0) {
                    echo "Please add a Bank record first.\n";
                } else {
                    $bankRow = $bankResult->fetch_assoc();
                    $bankId = (int) $bankRow["Bank_ID"];
                    $type = "DEBIT";
                    $sql = "INSERT INTO Transactions
                        (Customer_ID, Bank_ID, Transaction_Type,
                         Amount, Transaction_Date)
                        VALUES (?, ?, ?, ?, CURDATE())";
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param(
                        "iisd",
                        $customerId,
                        $bankId,
                        $type,
                        $amount
                    );
                    if ($stmt->execute()) {
                        echo "Money debited successfully.\n";
                    } else {
                        echo "Debit error: " . $stmt->error . "\n";
                    }
                    $stmt->close();
                }
            }
        }
        $check->close();
    }
}
//  Show All Transactions
elseif ($choice == 6) {
    $sql = "SELECT * FROM Transactions ORDER BY Transaction_ID";
    $result = $connection->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "\nTransaction ID: " . $row["Transaction_ID"] . "\n";
            echo "Customer ID: " . $row["Customer_ID"] . "\n";
            echo "Bank ID: " . $row["Bank_ID"] . "\n";
            echo "Type: " . $row["Transaction_Type"] . "\n";
            echo "Amount: Rs. " . $row["Amount"] . "\n";
            echo "Date: " . $row["Transaction_Date"] . "\n";
        }
    } else {
        echo "No transactions found.\n";
    }
}
// Update Customer
elseif ($choice == 7) {
    $customerId = (int) readline("Enter Customer ID to update: ");
    $check = $connection->prepare(
        "SELECT Customer_ID FROM Customer WHERE Customer_ID = ?"
    );
    $check->bind_param("i", $customerId);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows == 0) {
        echo "Customer ID does not exist.\n";
    } else {
        echo "Enter the new details.\n";
        $name = readline("Enter New Name: ");
        $age = (int) readline("Enter New Age: ");
        $gender = readline("Enter New Gender: ");
        $contact = readline("Enter New Contact: ");
        $email = readline("Enter New Email: ");
        $address = readline("Enter New Address: ");
        $updateSql = "UPDATE Customer
                      SET Name = ?, Age = ?, Gender = ?,
                          Contact = ?, Email = ?, Address = ?
                      WHERE Customer_ID = ?";
        $updateStmt = $connection->prepare($updateSql);
        $updateStmt->bind_param(
            "sissssi",
            $name,
            $age,
            $gender,
            $contact,
            $email,
            $address,
            $customerId
        );
        if ($updateStmt->execute()) {
            echo "Customer details updated successfully.\n";
            echo "Customer ID: " . $customerId . "\n";
            echo "Name: " . $name . "\n";
            echo "Age: " . $age . "\n";
            echo "Gender: " . $gender . "\n";
            echo "Contact: " . $contact . "\n";
            echo "Email: " . $email . "\n";
            echo "Address: " . $address . "\n";
        } else {
            echo "Update failed: " . $updateStmt->error . "\n";
        }
        $updateStmt->close();
    }
    $check->close();
}
// Option 8: Delete Customer
elseif ($choice == 8) {
    $customerId = (int) readline("Enter Customer ID to delete: ");
    // Check whether customer exists
    $check = $connection->prepare(
        "SELECT Customer_ID, Name FROM Customer WHERE Customer_ID = ?"
    );
    $check->bind_param("i", $customerId);
    $check->execute();
    $customerResult = $check->get_result();
    if ($customerResult->num_rows == 0) {
        echo "Customer ID does not exist.\n";
    } else {
        $customer = $customerResult->fetch_assoc();
        echo "Customer Name: " . $customer["Name"] . "\n";
        // Preserve transaction history
        $transactionCheck = $connection->prepare(
            "SELECT Transaction_ID
             FROM Transactions
             WHERE Customer_ID = ?
             LIMIT 1"
        );
        $transactionCheck->bind_param("i", $customerId);
        $transactionCheck->execute();
        $transactionResult = $transactionCheck->get_result();
        if ($transactionResult->num_rows > 0) {
            echo "Cannot delete this customer because transaction history exists.\n";
            echo "Transaction history has been preserved.\n";
        } else {
            $confirm = readline(
                "Are you sure you want to delete this customer? (yes/no): "
            );
            if (strtolower(trim($confirm)) == "yes") {
                $delete = $connection->prepare(
                    "DELETE FROM Customer WHERE Customer_ID = ?"
                );
                $delete->bind_param("i", $customerId);
                if ($delete->execute()) {
                    echo "Customer deleted successfully.\n";
                } else {
                    echo "Delete failed: " . $delete->error . "\n";
                }
                $delete->close();
            } else {
                echo "Customer deletion cancelled.\n";
            }
        }
        $transactionCheck->close();
    }
    $check->close();
}
elseif ($choice == 9) {
    echo "Thank you for using Horizon Bank.\n";
} else {
    echo "Invalid choice.\n";
}
$connection->close();
echo "Database connection closed.\n";
?>