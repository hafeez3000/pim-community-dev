@javascript
Feature: Display product attributes in the grid
  In order to easily see product data in the grid
  As a regular user
  I need to be able to display values for different attributes in the grid

  Scenario: Successfully display values for simple and multi select attributes
    Given a "footwear" catalog configuration
    And the following "manufacturer" attribute options: adidas and lacoste
    And the following "weather_conditions" attribute options: cloudy and stormy
    And the following products:
      | sku      | manufacturer | weather_conditions  |
      | sneakers | Converse     | dry, cloudy, stormy |
      | sandals  | adidas       | hot, dry            |
      | boots    | lacoste      | cloudy              |
    And I am logged in as "Mary"
    And I am on the products page
    When I display the columns sku, manufacturer and weather_conditions
    Then the row "sneakers" should contain:
      | column             | value                   |
      | Manufacturer       | Converse                |
      | Weather conditions | Dry, [cloudy], [stormy] |
    And the row "sandals" should contain:
      | column             | value    |
      | Manufacturer       | [adidas] |
      | Weather conditions | Dry, Hot |
    And the row "boots" should contain:
      | column             | value     |
      | Manufacturer       | [lacoste] |
      | Weather conditions | [cloudy]  |
