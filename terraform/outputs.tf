output "resource_group_name" {
    value = azurerm_resource_group.rg.name
}

output "public_ip_adress" {
    value = azurerm_linux_virtual_machine.my_terraform_vm.public_ip_address
}

output "vm_password" {
    value = random_string.password.result
}