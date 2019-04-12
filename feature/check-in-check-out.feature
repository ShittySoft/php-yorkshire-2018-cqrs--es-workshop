Feature: People can check in and out of a building

  Scenario: People can check into a building
    Given a building was registered
    When "bob" checks into the building
    Then "bob" should have been checked into the building

  Scenario: Checking in twice will lead to a check-in anomaly being detected
    Given a building was registered
    And "bob" checked into the building
    When "bob" checks into the building
    Then "bob" should have been checked into the building
    And a check-in anomaly caused by "bob" should have been detected

