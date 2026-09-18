<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticMcpBundle\Application\System\McpTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mautic:mcp:token',
    description: 'Issue, list or revoke MCP bearer tokens for a user.'
)]
final class McpTokenCommand extends Command
{
    public function __construct(
        private McpTokenService $tokens,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'issue, list or revoke', 'list')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Token owner: username, e-mail or numeric id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Token id to revoke, as shown by the list action')
            ->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Lifetime in seconds (default: one year)')
            ->setHelp(<<<'HELP'
Issuing never disturbs tokens already in use, so a new client can be connected
while the existing ones keep working:

  <info>php %command.full_name% issue --user=admin</info>
  <info>php %command.full_name% issue --user=admin --ttl=2592000</info>
  <info>php %command.full_name% list --user=admin</info>
  <info>php %command.full_name% revoke --user=admin --id=421</info>

To replace every token at once, use the rotate button on the profile page.
HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $action = strtolower(trim((string) $input->getArgument('action')));

        if (!in_array($action, ['issue', 'list', 'revoke'], true)) {
            $io->error(sprintf('Unknown action "%s". Use issue, list or revoke.', $action));

            return Command::INVALID;
        }

        try {
            $user = $this->resolveUser((string) $input->getOption('user'));
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        return match ($action) {
            'issue'  => $this->issue($io, $user, $input->getOption('ttl')),
            'revoke' => $this->revoke($io, $user, $input->getOption('id')),
            default  => $this->list($io, $user),
        };
    }

    private function list(SymfonyStyle $io, User $user): int
    {
        $tokens = $this->tokens->listActive($user);

        if ([] === $tokens) {
            $io->warning(sprintf('No active MCP token for %s.', $user->getUsername()));

            return Command::SUCCESS;
        }

        $io->table(
            ['id', 'token', 'expires at'],
            array_map(static fn (array $token): array => [$token['id'], $token['preview'], $token['expiresAt']], $tokens)
        );
        $io->note('Values are truncated on purpose. Copy an existing token from the MCP Access panel on the profile page.');

        return Command::SUCCESS;
    }

    private function issue(SymfonyStyle $io, User $user, ?string $ttl): int
    {
        if (null !== $ttl && (!ctype_digit($ttl) || (int) $ttl < 1)) {
            $io->error('--ttl must be a whole number of seconds greater than zero.');

            return Command::INVALID;
        }

        $issued = $this->tokens->issue($user, null === $ttl ? null : (int) $ttl);

        $io->success(sprintf('Token #%d issued for %s. Tokens already in use keep working.', $issued['id'], $user->getUsername()));
        $io->writeln($issued['token']);
        $io->newLine();
        $io->note(sprintf('Expires at %s. Treat it like a password and store it in a secret manager.', $issued['expiresAt']));

        return Command::SUCCESS;
    }

    private function revoke(SymfonyStyle $io, User $user, ?string $id): int
    {
        if (null === $id || !ctype_digit($id)) {
            $io->error('Pass the token to revoke with --id (see the list action).');

            return Command::INVALID;
        }

        if (!$this->tokens->revokeById($user, (int) $id)) {
            $io->error(sprintf('No MCP token #%s belonging to %s.', $id, $user->getUsername()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Token #%s revoked. The remaining tokens keep working.', $id));

        return Command::SUCCESS;
    }

    private function resolveUser(string $identifier): User
    {
        $identifier = trim($identifier);

        if ('' === $identifier) {
            throw new \RuntimeException('Name the token owner with --user (username, e-mail or numeric id).');
        }

        $repository = $this->entityManager->getRepository(User::class);

        $user = ctype_digit($identifier)
            ? $repository->find((int) $identifier)
            : ($repository->findOneBy(['username' => $identifier]) ?? $repository->findOneBy(['email' => $identifier]));

        if (!$user instanceof User) {
            throw new \RuntimeException(sprintf('No user matches "%s".', $identifier));
        }

        return $user;
    }
}
