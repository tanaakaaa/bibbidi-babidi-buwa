using System;

class Program
{
    static void Main()
    {
        Console.Write("Digite o primeiro número: ");
        int numero1 = int.Parse(Console.ReadLine());

        Console.Write("Digite o segundo número: ");
        int numero2 = int.Parse(Console.ReadLine());

        int soma = numero1 + numero2;

        Console.WriteLine("A soma é: " + soma);
    }
}
