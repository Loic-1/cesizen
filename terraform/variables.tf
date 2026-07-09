variable "prefix" {
  type = string
  default = "cesizen"
  description = "Prefix of the resource names"
}

variable "resource_location" {
  type = string
  default = "switzerlandnorth"
  description = "Location of the resources"
}

variable "username" {
  type = string
  default = "cesizenadm"
  description = "VM local account username"
}