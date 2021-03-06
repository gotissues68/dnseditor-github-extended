USE [dns_data]
GO
/****** Object:  Table [dbo].[techiekb_com]    Script Date: 04/22/2010 12:54:36 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[techiekb_com](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[zone] [varchar](max) NULL CONSTRAINT [DF__techiekb_c__zone__7C8480AE]  DEFAULT (NULL),
	[host] [varchar](max) NULL CONSTRAINT [DF__techiekb_c__host__7D78A4E7]  DEFAULT (NULL),
	[type] [varchar](max) NULL CONSTRAINT [DF__techiekb_c__type__7E6CC920]  DEFAULT (NULL),
	[data] [varchar](max) NULL CONSTRAINT [DF__techiekb_c__data__7F60ED59]  DEFAULT (NULL),
	[ttl] [int] NULL CONSTRAINT [DF__techiekb_co__ttl__00551192]  DEFAULT (NULL),
	[mx_priority] [varchar](max) NULL CONSTRAINT [DF__techiekb___mx_pr__014935CB]  DEFAULT (NULL),
	[refresh] [int] NULL CONSTRAINT [DF__techiekb___refre__023D5A04]  DEFAULT (NULL),
	[retry] [int] NULL CONSTRAINT [DF__techiekb___retry__03317E3D]  DEFAULT (NULL),
	[expire] [int] NULL CONSTRAINT [DF__techiekb___expir__0425A276]  DEFAULT (NULL),
	[minimum] [int] NULL CONSTRAINT [DF__techiekb___minim__0519C6AF]  DEFAULT (NULL),
	[serial] [bigint] NULL CONSTRAINT [DF__techiekb___seria__060DEAE8]  DEFAULT (NULL),
	[resp_person] [varchar](max) NULL CONSTRAINT [DF__techiekb___resp___07020F21]  DEFAULT (NULL),
	[primary_ns] [varchar](max) NULL CONSTRAINT [DF__techiekb___prima__07F6335A]  DEFAULT (NULL),
	[owner] [varchar](255) NULL CONSTRAINT [DF__techiekb___owner__08EA5793]  DEFAULT (NULL)
) ON [PRIMARY]
GO
SET ANSI_PADDING OFF
GO
