terraform {
  required_version = ">=0.12"

  required_providers {
    random = {
        source = "hashicorp/random"
        version = "~>3.0"
    }
    azurerm = {
        source = "hashicorp/azurerm"
        version = "~>4.0"
    }
  }
}

provider "azurerm" {
  features {}
}
